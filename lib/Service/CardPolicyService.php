<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use DateTime;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\BoardPolicyDefaultRole;
use OCA\Deck\Db\BoardPolicyDefaultRoleMapper;
use OCA\Deck\Db\BoardPolicyRole;
use OCA\Deck\Db\BoardPolicyRoleMapper;
use OCA\Deck\Db\BoardPolicyRoleMembership;
use OCA\Deck\Db\BoardPolicyRoleMembershipMapper;
use OCA\Deck\Db\BoardPolicySetting;
use OCA\Deck\Db\BoardPolicySettingMapper;
use OCA\Deck\Db\CardPolicy;
use OCA\Deck\Db\CardPolicyMapper;
use OCA\Deck\Db\CardPolicyRole;
use OCA\Deck\Db\CardPolicyRoleMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IGroupManager;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class CardPolicyService
{
	public const ACTION_MOVE = BoardPolicyDefaultRole::ACTION_MOVE;
	public const ACTION_APPROVE = BoardPolicyDefaultRole::ACTION_APPROVE;
	// Future: separate action for visibility (not wired to UI yet)
	public const ACTION_VIEW = 'view';

	/** @var array<string, int[]> */
	private array $userRoleIdsCache = [];
	/** @var array<int, int[]> */
	private array $defaultUnionRoleIdsCache = [];
	/** @var array<string, bool> */
	private array $fullVisibilityCache = [];

	/**
	 * Default roles for new card_policy boards.
	 * Keys are stable API identifiers.
	 */
	public const DEFAULT_ROLES = [
		['key' => 'client_developer', 'name' => 'Client/Developer', 'color' => '#111111'],
		['key' => 'cpl', 'name' => 'CPL', 'color' => '#10b981'],
		['key' => 'grid_operator', 'name' => 'Grid operator (Elektra)', 'color' => '#f97316'],
	];

	public function __construct(
		private readonly IDBConnection $db,
		private readonly BoardPolicySettingMapper $settingMapper,
		private readonly BoardPolicyRoleMapper $roleMapper,
		private readonly BoardPolicyRoleMembershipMapper $membershipMapper,
		private readonly BoardPolicyDefaultRoleMapper $defaultRoleMapper,
		private readonly CardPolicyMapper $cardPolicyMapper,
		private readonly CardPolicyRoleMapper $cardPolicyRoleMapper,
		private readonly StackMapper $stackMapper,
		private readonly PermissionService $permissionService,
		private readonly NotificationHelper $notificationHelper,
		private readonly CirclesService $circlesService,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
		private readonly ?string $userId,
	) {
	}

	public function isCardPolicyEnabled(int $boardId): bool
	{
		$setting = $this->findSettingOrNull($boardId);
		return $setting instanceof BoardPolicySetting
			&& $setting->getPermissionMode() === BoardPolicySetting::MODE_CARD_POLICY;
	}

	/**
	 * Card visibility for card-policy boards.
	 *
	 * For now visibility is derived from roles configured for move/approve:
	 * - users can see a card if they match ANY role that is allowed to move OR approve the card
	 * - board owners / board managers / project owner / organization admins can see everything
	 */
	public function assertUserAllowedToViewCard(int $boardId, int $cardId, ?string $userId = null): void
	{
		$uid = $this->resolveUserId($userId);
		if ($uid === '') {
			throw new NoPermissionException('Permission denied');
		}

		$setting = $this->findSettingOrNull($boardId);
		if (!$setting instanceof BoardPolicySetting || $setting->getPermissionMode() !== BoardPolicySetting::MODE_CARD_POLICY) {
			return;
		}

		if ($this->userHasFullVisibilityForBoard($boardId, $uid)) {
			return;
		}

		$explicitViewRoleIds = $this->getExplicitRoleIdsForCardAndAction($boardId, $cardId, self::ACTION_VIEW);
		$defaultViewRoleIds = $this->getDefaultRoleIdsForAction($boardId, self::ACTION_VIEW);
		$allowedRoleIds = $explicitViewRoleIds !== []
			? $explicitViewRoleIds
			: ($defaultViewRoleIds !== []
				? $defaultViewRoleIds
				: array_values(array_unique(array_merge(
					$this->getEffectiveRoleIdsForCard($boardId, $cardId, self::ACTION_MOVE),
					$this->getEffectiveRoleIdsForCard($boardId, $cardId, self::ACTION_APPROVE),
				))));
		$allowedRoleIds = array_values(array_filter($allowedRoleIds, static fn (int $id) => $id > 0));
		if ($allowedRoleIds === []) {
			throw new NoPermissionException('Permission denied');
		}

		$memberships = $this->membershipMapper->findByBoardAndRoleIds($boardId, $allowedRoleIds);
		foreach ($memberships as $membership) {
			if ($this->membershipMatchesUser($membership, $uid)) {
				return;
			}
		}

		throw new NoPermissionException('Permission denied');
	}

	/**
	 * @return int[]
	 */
	private function getExplicitRoleIdsForCardAndAction(int $boardId, int $cardId, string $action): array
	{
		$policy = $this->cardPolicyMapper->findByBoardAndCard($boardId, $cardId);
		if (!$policy instanceof CardPolicy) {
			return [];
		}
		$roles = $this->cardPolicyRoleMapper->findByPolicyAndAction((int) $policy->getId(), $action);
		$roleIds = array_map(static fn (CardPolicyRole $r) => (int) $r->getRoleId(), $roles);
		return array_values(array_unique(array_filter($roleIds, static fn (int $id) => $id > 0)));
	}

	/**
	 * Filters card entities to only those visible to the user.
	 *
	 * @param array<int, object> $cards expects objects with getId()
	 * @return array<int, object>
	 */
	public function filterCardsForUser(int $boardId, ?string $userId, array $cards): array
	{
		$uid = $this->resolveUserId($userId);
		if ($uid === '' || $cards === []) {
			return [];
		}

		$setting = $this->findSettingOrNull($boardId);
		if (!$setting instanceof BoardPolicySetting || $setting->getPermissionMode() !== BoardPolicySetting::MODE_CARD_POLICY) {
			return $cards;
		}

		if ($this->userHasFullVisibilityForBoard($boardId, $uid)) {
			return $cards;
		}

		$userRoleIds = $this->getUserRoleIdsForBoard($boardId, $uid);
		if ($userRoleIds === []) {
			return [];
		}

		$defaultAllowedRoleIds = $this->getDefaultUnionRoleIds($boardId);
		$defaultViewRoleIds = $this->getDefaultRoleIdsForAction($boardId, self::ACTION_VIEW);
		$cardIds = [];
		foreach ($cards as $card) {
			if (!is_object($card) || !method_exists($card, 'getId')) {
				continue;
			}
			$cardId = (int) $card->getId();
			if ($cardId > 0) {
				$cardIds[] = $cardId;
			}
		}
		$cardIds = array_values(array_unique($cardIds));
		if ($cardIds === []) {
			return [];
		}

		$explicitAllowedByCardId = $this->getExplicitRoleIdsForCardsAndActions($boardId, $cardIds, [self::ACTION_MOVE, self::ACTION_APPROVE]);
		$explicitViewByCardId = $this->getExplicitRoleIdsForCardsAndActions($boardId, $cardIds, [self::ACTION_VIEW]);

		$out = [];
		$userRoleSet = array_fill_keys($userRoleIds, true);
		foreach ($cards as $card) {
			if (!is_object($card) || !method_exists($card, 'getId')) {
				continue;
			}
			$cardId = (int) $card->getId();
			if ($cardId <= 0) {
				continue;
			}

			if (isset($explicitViewByCardId[$cardId])) {
				$allowed = $explicitViewByCardId[$cardId];
			} elseif ($defaultViewRoleIds !== []) {
				$allowed = $defaultViewRoleIds;
			} else {
				$allowed = $explicitAllowedByCardId[$cardId] ?? $defaultAllowedRoleIds;
			}
			if ($allowed === []) {
				continue;
			}
			foreach ($allowed as $rid) {
				if (isset($userRoleSet[$rid])) {
					$out[] = $card;
					break;
				}
			}
		}

		return $out;
	}

	public function getApprovedStackId(int $boardId): ?int
	{
		$setting = $this->ensureBoardSettings($boardId);
		$approved = $setting->getApprovedStackId();
		return $approved !== null ? (int) $approved : null;
	}

	public function enableCardPolicyMode(int $boardId): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);

		$setting = $this->ensureBoardSettings($boardId);
		$setting->setPermissionMode(BoardPolicySetting::MODE_CARD_POLICY);
		$setting->setUpdatedAt(new DateTime('now'));
		$this->settingMapper->update($setting);

		$this->ensureDefaultRolesAndDefaults($boardId);
	}

	/**
	 * Ensure settings exist and approved_stack_id is persisted.
	 *
	 * If approvedStackId is not provided and missing in DB, it is inferred once from current stacks:
	 * - prefer stack titled "Approved/Done" (exact match)
	 * - else pick the stack with highest order
	 */
	public function ensureBoardSettings(int $boardId, ?int $approvedStackId = null): BoardPolicySetting
	{
		$setting = $this->findSettingOrNull($boardId);
		$now = new DateTime('now');

		if (!$setting instanceof BoardPolicySetting) {
			$setting = new BoardPolicySetting();
			$setting->setBoardId($boardId);
			$setting->setPermissionMode(BoardPolicySetting::MODE_LEGACY);
			$setting->setCreatedAt($now);
			$setting->setUpdatedAt(null);
			$setting->setApprovedStackId(null);
			$setting = $this->settingMapper->insert($setting);
		}

		if ($approvedStackId !== null) {
			$setting->setApprovedStackId($approvedStackId);
			$setting->setUpdatedAt($now);
			return $this->settingMapper->update($setting);
		}

		if ($setting->getApprovedStackId() !== null) {
			return $setting;
		}

		try {
			$stacks = $this->stackMapper->findAll($boardId);
		} catch (\Throwable $e) {
			$this->logger->warning('Card policy settings: unable to load stacks for approved stack inference', [
				'exception' => $e,
				'boardId' => $boardId,
			]);
			return $setting;
		}

		$approvedId = null;
		$maxOrder = null;
		foreach ($stacks as $stack) {
			$title = (string) ($stack->getTitle() ?? '');
			$order = (int) ($stack->getOrder() ?? -1);
			if ($title === 'Approved/Done') {
				$approvedId = (int) $stack->getId();
				break;
			}
			if ($maxOrder === null || $order > $maxOrder) {
				$maxOrder = $order;
				$approvedId = (int) $stack->getId();
			}
		}

		if ($approvedId !== null && $approvedId > 0) {
			$setting->setApprovedStackId($approvedId);
			$setting->setUpdatedAt($now);
			$setting = $this->settingMapper->update($setting);
		}

		return $setting;
	}

	/**
	 * Return full policy state for a board (for manager UI).
	 */
	public function getBoardPolicyState(int $boardId): array
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$setting = $this->ensureBoardSettings($boardId);
		$roles = $this->roleMapper->findByBoard($boardId);
		$memberships = $this->membershipMapper->findByBoard($boardId);
		$defaults = $this->defaultRoleMapper->findByBoard($boardId);
		$defaultRolesByAction = $this->getDefaultRoleKeysByAction($boardId);

		return [
			'settings' => [
				'permissionMode' => (string) $setting->getPermissionMode(),
				'approvedStackId' => $setting->getApprovedStackId() !== null ? (int) $setting->getApprovedStackId() : null,
			],
			'roles' => array_map(static fn (BoardPolicyRole $r) => $r->jsonSerialize(), $roles),
			'memberships' => array_map(static fn (BoardPolicyRoleMembership $m) => $m->jsonSerialize(), $memberships),
			'defaultRoles' => array_map(static fn (BoardPolicyDefaultRole $d) => $d->jsonSerialize(), $defaults),
			'defaultRoleKeys' => $defaultRolesByAction,
		];
	}

	/**
	 * @return array{move: string[], approve: string[], view: string[]}
	 */
	public function getDefaultRoleKeysByAction(int $boardId): array
	{
		$defaults = $this->defaultRoleMapper->findByBoard($boardId);
		$roles = $this->roleMapper->findByBoard($boardId);
		$roleKeyById = [];
		foreach ($roles as $r) {
			$roleKeyById[(int) $r->getId()] = (string) $r->getRoleKey();
		}
		$out = ['move' => [], 'approve' => [], 'view' => []];
		foreach ($defaults as $d) {
			$action = (string) $d->getAction();
			$roleId = (int) $d->getRoleId();
			if (!isset($out[$action]) || !isset($roleKeyById[$roleId])) {
				continue;
			}
			$out[$action][] = $roleKeyById[$roleId];
		}
		$out['move'] = array_values(array_unique($out['move']));
		$out['approve'] = array_values(array_unique($out['approve']));
		$out['view'] = array_values(array_unique($out['view']));
		return $out;
	}

	/**
	 * Returns explicit per-card policies keyed by cardId.
	 *
	 * @return array<int, array{move: string[], approve: string[], view: string[]}>
	 */
	public function getExplicitCardPoliciesByBoard(int $boardId): array
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);

		$qb = $this->db->getQueryBuilder();
		$qb->select('cp.card_id', 'cpr.action', 'r.role_key')
			->from('deck_card_policy', 'cp')
			->innerJoin('cp', 'deck_card_policy_roles', 'cpr', $qb->expr()->eq('cp.id', 'cpr.policy_id'))
			->innerJoin('cpr', 'deck_board_policy_roles', 'r', $qb->expr()->eq('cpr.role_id', 'r.id'))
			->where($qb->expr()->eq('cp.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('r.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$cardId = (int) ($row['card_id'] ?? 0);
			$action = (string) ($row['action'] ?? '');
			$roleKey = (string) ($row['role_key'] ?? '');
			if ($cardId <= 0 || ($action !== self::ACTION_MOVE && $action !== self::ACTION_APPROVE && $action !== self::ACTION_VIEW) || $roleKey === '') {
				continue;
			}
			if (!isset($out[$cardId])) {
				$out[$cardId] = ['move' => [], 'approve' => [], 'view' => []];
			}
			$out[$cardId][$action][] = $roleKey;
		}
		$result->closeCursor();

		foreach ($out as $cardId => $actions) {
			$out[$cardId]['move'] = array_values(array_unique($actions['move'] ?? []));
			$out[$cardId]['approve'] = array_values(array_unique($actions['approve'] ?? []));
			$out[$cardId]['view'] = array_values(array_unique($actions['view'] ?? []));
		}
		return $out;
	}

	public function setApprovedStackId(int $boardId, int $stackId): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$setting = $this->ensureBoardSettings($boardId);
		$setting->setApprovedStackId($stackId);
		$setting->setUpdatedAt(new DateTime('now'));
		$this->settingMapper->update($setting);
	}

	/**
	 * @param string[] $moveRoleKeys
	 * @param string[] $approveRoleKeys
	 * @param string[] $viewRoleKeys
	 */
	public function setBoardDefaultRolesByKeys(int $boardId, array $moveRoleKeys, array $approveRoleKeys, array $viewRoleKeys = []): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$this->ensureDefaultRolesAndDefaults($boardId);

		$moveRoleIds = $this->resolveRoleIdsByKeys($boardId, $moveRoleKeys);
		$approveRoleIds = $this->resolveRoleIdsByKeys($boardId, $approveRoleKeys);
		$viewRoleIds = $this->resolveRoleIdsByKeys($boardId, $viewRoleKeys);
		$this->replaceDefaultRoles($boardId, self::ACTION_MOVE, $moveRoleIds);
		$this->replaceDefaultRoles($boardId, self::ACTION_APPROVE, $approveRoleIds);
		$this->replaceDefaultRoles($boardId, self::ACTION_VIEW, $viewRoleIds);
	}

	/**
	 * @param string[] $moveRoleKeys
	 * @param string[] $approveRoleKeys
	 * @param string[] $viewRoleKeys
	 */
	public function setCardPolicyByRoleKeys(int $boardId, int $cardId, array $moveRoleKeys, array $approveRoleKeys, array $viewRoleKeys = []): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$this->ensureDefaultRolesAndDefaults($boardId);

		$moveRoleIds = $this->resolveRoleIdsByKeys($boardId, $moveRoleKeys);
		$approveRoleIds = $this->resolveRoleIdsByKeys($boardId, $approveRoleKeys);
		$viewRoleIds = $this->resolveRoleIdsByKeys($boardId, $viewRoleKeys);

		$policy = $this->cardPolicyMapper->findByBoardAndCard($boardId, $cardId);
		$now = new DateTime('now');
		if (!$policy instanceof CardPolicy) {
			$policy = new CardPolicy();
			$policy->setBoardId($boardId);
			$policy->setCardId($cardId);
			$policy->setCreatedAt($now);
			$policy->setUpdatedAt(null);
			$policy = $this->cardPolicyMapper->insert($policy);
		} else {
			$policy->setUpdatedAt($now);
			$policy = $this->cardPolicyMapper->update($policy);
		}

		$this->replaceCardPolicyRoles((int) $policy->getId(), self::ACTION_MOVE, $moveRoleIds);
		$this->replaceCardPolicyRoles((int) $policy->getId(), self::ACTION_APPROVE, $approveRoleIds);
		$this->replaceCardPolicyRoles((int) $policy->getId(), self::ACTION_VIEW, $viewRoleIds);
	}

	public function clearCardPolicy(int $boardId, int $cardId): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$policy = $this->cardPolicyMapper->findByBoardAndCard($boardId, $cardId);
		if (!$policy instanceof CardPolicy) {
			return;
		}
		$policyId = (int) $policy->getId();
		$this->deleteCardPolicyRoles($policyId);
		$this->cardPolicyMapper->delete($policy);
	}

	public function addMembership(int $boardId, string $roleKey, string $participant, int $participantType): BoardPolicyRoleMembership
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$this->ensureDefaultRolesAndDefaults($boardId);
		$role = $this->roleMapper->findByBoardAndKey($boardId, $roleKey);
		if (!$role instanceof BoardPolicyRole) {
			throw new BadRequestException('Unknown role');
		}

		if (!in_array($participantType, [
			BoardPolicyRoleMembership::PARTICIPANT_TYPE_USER,
			BoardPolicyRoleMembership::PARTICIPANT_TYPE_GROUP,
			BoardPolicyRoleMembership::PARTICIPANT_TYPE_CIRCLE,
		], true)) {
			throw new BadRequestException('Invalid participant type');
		}

		$membership = new BoardPolicyRoleMembership();
		$membership->setBoardId($boardId);
		$membership->setRoleId((int) $role->getId());
		$membership->setParticipant($participant);
		$membership->setParticipantType($participantType);
		$membership->setCreatedAt(new DateTime('now'));
		$membership = $this->membershipMapper->insert($membership);

		if (
			$participantType === BoardPolicyRoleMembership::PARTICIPANT_TYPE_USER
			&& $participant !== ''
			&& $participant !== (string) $this->userId
		) {
			$this->notificationHelper->sendBoardRoleAssigned($boardId, (string) $role->getName(), $participant);
		}

		return $membership;
	}

	public function createRole(int $boardId, string $roleKey, string $name, string $color = '#000000'): BoardPolicyRole
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$roleKey = trim($roleKey);
		$name = trim($name);
		$color = trim($color);

		if ($roleKey === '' || $name === '') {
			throw new BadRequestException('Role key and name are required');
		}
		if (!preg_match('/^[a-z0-9_\-]+$/', $roleKey)) {
			throw new BadRequestException('Role key must be lowercase and URL-safe');
		}
		if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
			throw new BadRequestException('Invalid color');
		}

		$existing = $this->roleMapper->findByBoardAndKey($boardId, $roleKey);
		if ($existing instanceof BoardPolicyRole) {
			throw new BadRequestException('Role key already exists');
		}

		$role = new BoardPolicyRole();
		$role->setBoardId($boardId);
		$role->setRoleKey($roleKey);
		$role->setName($name);
		$role->setColor($color);
		$role->setCreatedAt(new DateTime('now'));
		$role->setUpdatedAt(null);
		return $this->roleMapper->insert($role);
	}

	public function updateRole(int $boardId, int $roleId, ?string $name = null, ?string $color = null): BoardPolicyRole
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		/** @var BoardPolicyRole $role */
		$role = $this->roleMapper->find($roleId);
		if ((int) $role->getBoardId() !== $boardId) {
			throw new NoPermissionException('Role does not belong to this board');
		}
		if ($name !== null) {
			$name = trim($name);
			if ($name === '') {
				throw new BadRequestException('Invalid role name');
			}
			$role->setName($name);
		}
		if ($color !== null) {
			$color = trim($color);
			if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
				throw new BadRequestException('Invalid color');
			}
			$role->setColor($color);
		}
		$role->setUpdatedAt(new DateTime('now'));
		return $this->roleMapper->update($role);
	}

	public function deleteRole(int $boardId, int $roleId): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		/** @var BoardPolicyRole $role */
		$role = $this->roleMapper->find($roleId);
		if ((int) $role->getBoardId() !== $boardId) {
			throw new NoPermissionException('Role does not belong to this board');
		}

		// Remove memberships
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_board_policy_role_memberships')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		// Remove defaults
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_board_policy_default_roles')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		// Remove role from any card policies
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_card_policy_roles')
			->where($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		$this->roleMapper->delete($role);
	}

	public function deleteMembership(int $boardId, int $membershipId): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$membership = $this->membershipMapper->find($membershipId);
		if ((int) $membership->getBoardId() !== $boardId) {
			throw new NoPermissionException('Membership does not belong to this board');
		}
		$this->membershipMapper->delete($membership);
	}

	/**
	 * Permission checks used by CardService.
	 */
	public function assertUserAllowedForAction(int $boardId, int $cardId, string $action, string $userId): void
	{
		$setting = $this->findSettingOrNull($boardId);
		if (!$setting instanceof BoardPolicySetting || $setting->getPermissionMode() !== BoardPolicySetting::MODE_CARD_POLICY) {
			return;
		}

		if ($this->groupManager->isAdmin($userId) || $this->permissionService->userIsBoardOwner($boardId, $userId)) {
			return;
		}

		if (!in_array($action, [self::ACTION_MOVE, self::ACTION_APPROVE], true)) {
			throw new NoPermissionException('Unknown policy action');
		}

		$allowedRoleIds = $this->getEffectiveRoleIdsForCard($boardId, $cardId, $action);
		if ($allowedRoleIds === []) {
			throw new NoPermissionException('No roles configured for this action');
		}

		$memberships = $this->membershipMapper->findByBoardAndRoleIds($boardId, $allowedRoleIds);
		foreach ($memberships as $membership) {
			if ($this->membershipMatchesUser($membership, $userId)) {
				return;
			}
		}

		throw new NoPermissionException('You do not have permission to perform this action.');
	}

	private function resolveUserId(?string $userId): string
	{
		$userId = $userId !== null ? trim($userId) : trim((string) ($this->userId ?? ''));
		return $userId;
	}

	/**
	 * Project/organization aware bypass.
	 */
	private function userHasFullVisibilityForBoard(int $boardId, string $userId): bool
	{
		$userId = trim($userId);
		if ($userId === '' || $boardId <= 0) {
			return false;
		}

		$cacheKey = $boardId . ':' . $userId;
		if (array_key_exists($cacheKey, $this->fullVisibilityCache)) {
			return $this->fullVisibilityCache[$cacheKey];
		}

		$visible = false;
		try {
			if ($this->groupManager->isAdmin($userId)) {
				$visible = true;
			} elseif ($this->permissionService->userIsBoardOwner($boardId, $userId)) {
				$visible = true;
			} else {
				$project = $this->findProjectByBoardId($boardId);
				if ($project !== null) {
					$ownerId = trim((string) ($project['owner_id'] ?? ''));
					if ($ownerId !== '' && $ownerId === $userId) {
						$visible = true;
					} else {
						$orgId = isset($project['organization_id']) ? (int) $project['organization_id'] : 0;
						if ($orgId > 0 && $this->userIsOrganizationAdmin($userId, $orgId)) {
							$visible = true;
						}
					}
				}
			}
		} catch (\Throwable $e) {
			$visible = false;
		}

		$this->fullVisibilityCache[$cacheKey] = $visible;
		return $visible;
	}

	/**
	 * @return array{owner_id:?string, organization_id:?int}|null
	 */
	private function findProjectByBoardId(int $boardId): ?array
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('owner_id', 'organization_id')
				->from('custom_projects')
				->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
				->setMaxResults(1);

			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();
			if ($row === false) {
				return null;
			}
			return [
				'owner_id' => isset($row['owner_id']) ? (string) $row['owner_id'] : null,
				'organization_id' => isset($row['organization_id']) && $row['organization_id'] !== null ? (int) $row['organization_id'] : null,
			];
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function userIsOrganizationAdmin(string $userId, int $organizationId): bool
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('user_uid')
				->from('organization_members')
				->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('role', $qb->createNamedParameter('admin', IQueryBuilder::PARAM_STR)))
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();
			return $row !== false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * @return int[]
	 */
	private function getUserRoleIdsForBoard(int $boardId, string $userId): array
	{
		$cacheKey = $boardId . ':' . $userId;
		if (isset($this->userRoleIdsCache[$cacheKey])) {
			return $this->userRoleIdsCache[$cacheKey];
		}

		$memberships = $this->membershipMapper->findByBoard($boardId);
		$out = [];
		foreach ($memberships as $membership) {
			if ($this->membershipMatchesUser($membership, $userId)) {
				$out[] = (int) $membership->getRoleId();
			}
		}
		$out = array_values(array_unique(array_filter($out, static fn (int $id) => $id > 0)));
		$this->userRoleIdsCache[$cacheKey] = $out;
		return $out;
	}

	/**
	 * Union of default roles for move+approve.
	 *
	 * @return int[]
	 */
	private function getDefaultUnionRoleIds(int $boardId): array
	{
		if (isset($this->defaultUnionRoleIdsCache[$boardId])) {
			return $this->defaultUnionRoleIdsCache[$boardId];
		}
		$move = $this->defaultRoleMapper->findByBoardAndAction($boardId, self::ACTION_MOVE);
		$approve = $this->defaultRoleMapper->findByBoardAndAction($boardId, self::ACTION_APPROVE);
		$roleIds = array_merge(
			array_map(static fn (BoardPolicyDefaultRole $d) => (int) $d->getRoleId(), $move),
			array_map(static fn (BoardPolicyDefaultRole $d) => (int) $d->getRoleId(), $approve),
		);
		$roleIds = array_values(array_unique(array_filter($roleIds, static fn (int $id) => $id > 0)));
		$this->defaultUnionRoleIdsCache[$boardId] = $roleIds;
		return $roleIds;
	}

	/**
	 * @return int[]
	 */
	private function getDefaultRoleIdsForAction(int $boardId, string $action): array
	{
		$defaults = $this->defaultRoleMapper->findByBoardAndAction($boardId, $action);
		$roleIds = array_map(static fn (BoardPolicyDefaultRole $d) => (int) $d->getRoleId(), $defaults);
		return array_values(array_unique(array_filter($roleIds, static fn (int $id) => $id > 0)));
	}

	/**
	 * @param int[] $cardIds
	 * @return array<int, int[]> cardId => roleIds
	 */
	private function getExplicitRoleIdsForCardsAndActions(int $boardId, array $cardIds, array $actions): array
	{
		if ($cardIds === [] || $actions === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('cp.card_id', 'cpr.role_id')
			->from('deck_card_policy', 'cp')
			->innerJoin('cp', 'deck_card_policy_roles', 'cpr', $qb->expr()->eq('cp.id', 'cpr.policy_id'))
			->where($qb->expr()->eq('cp.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('cp.card_id', $qb->createNamedParameter($cardIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->in('cpr.action', $qb->createNamedParameter(array_values($actions), IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$cardId = (int) ($row['card_id'] ?? 0);
			$roleId = (int) ($row['role_id'] ?? 0);
			if ($cardId <= 0 || $roleId <= 0) {
				continue;
			}
			if (!isset($out[$cardId])) {
				$out[$cardId] = [];
			}
			$out[$cardId][] = $roleId;
		}
		$result->closeCursor();

		foreach ($out as $cid => $roleIds) {
			$out[$cid] = array_values(array_unique(array_filter($roleIds, static fn (int $id) => $id > 0)));
		}

		return $out;
	}

	private function membershipMatchesUser(BoardPolicyRoleMembership $membership, string $userId): bool
	{
		switch ((int) $membership->getParticipantType()) {
			case BoardPolicyRoleMembership::PARTICIPANT_TYPE_USER:
				return (string) $membership->getParticipant() === $userId;
			case BoardPolicyRoleMembership::PARTICIPANT_TYPE_GROUP:
				return $this->groupManager->isInGroup($userId, (string) $membership->getParticipant());
			case BoardPolicyRoleMembership::PARTICIPANT_TYPE_CIRCLE:
				if (!$this->circlesService->isCirclesEnabled()) {
					return false;
				}
				try {
					return $this->circlesService->isUserInCircle((string) $membership->getParticipant(), $userId);
				} catch (\Throwable $e) {
					return false;
				}
			default:
				return false;
		}
	}

	/**
	 * @return int[]
	 */
	private function getEffectiveRoleIdsForCard(int $boardId, int $cardId, string $action): array
	{
		$policy = $this->cardPolicyMapper->findByBoardAndCard($boardId, $cardId);
		if ($policy instanceof CardPolicy) {
			$roles = $this->cardPolicyRoleMapper->findByPolicyAndAction((int) $policy->getId(), $action);
			$roleIds = array_map(static fn (CardPolicyRole $r) => (int) $r->getRoleId(), $roles);
			return array_values(array_unique(array_filter($roleIds, static fn (int $id) => $id > 0)));
		}

		$defaults = $this->defaultRoleMapper->findByBoardAndAction($boardId, $action);
		$roleIds = array_map(static fn (BoardPolicyDefaultRole $d) => (int) $d->getRoleId(), $defaults);
		return array_values(array_unique(array_filter($roleIds, static fn (int $id) => $id > 0)));
	}

	private function ensureDefaultRolesAndDefaults(int $boardId): void
	{
		$setting = $this->ensureBoardSettings($boardId);
		$roles = $this->roleMapper->findByBoard($boardId);
		if ($roles === []) {
			$now = new DateTime('now');
			foreach (self::DEFAULT_ROLES as $roleTemplate) {
				$role = new BoardPolicyRole();
				$role->setBoardId($boardId);
				$role->setRoleKey((string) $roleTemplate['key']);
				$role->setName((string) $roleTemplate['name']);
				$role->setColor((string) $roleTemplate['color']);
				$role->setCreatedAt($now);
				$role->setUpdatedAt(null);
				$this->roleMapper->insert($role);
			}
			$roles = $this->roleMapper->findByBoard($boardId);
		}

		$defaultMove = $this->defaultRoleMapper->findByBoardAndAction($boardId, self::ACTION_MOVE);
		$defaultApprove = $this->defaultRoleMapper->findByBoardAndAction($boardId, self::ACTION_APPROVE);
		if ($defaultMove !== [] && $defaultApprove !== []) {
			return;
		}

		$rolesByKey = [];
		foreach ($roles as $role) {
			$rolesByKey[(string) $role->getRoleKey()] = (int) $role->getId();
		}

		// Defaults: move -> all roles; approve -> CPL + Grid operator
		$moveRoleIds = array_values($rolesByKey);
		$approveRoleIds = array_values(array_filter([
			$rolesByKey['cpl'] ?? null,
			$rolesByKey['grid_operator'] ?? null,
		], static fn ($v) => is_int($v) && $v > 0));

		$this->replaceDefaultRoles($boardId, self::ACTION_MOVE, $moveRoleIds);
		$this->replaceDefaultRoles($boardId, self::ACTION_APPROVE, $approveRoleIds);
	}

	/**
	 * @param int[] $roleIds
	 */
	private function replaceDefaultRoles(int $boardId, string $action, array $roleIds): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_board_policy_default_roles')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();

		foreach (array_values(array_unique($roleIds)) as $roleId) {
			if (!is_int($roleId) || $roleId <= 0) {
				continue;
			}
			$default = new BoardPolicyDefaultRole();
			$default->setBoardId($boardId);
			$default->setAction($action);
			$default->setRoleId($roleId);
			$this->defaultRoleMapper->insert($default);
		}
	}

	/**
	 * @param int[] $roleIds
	 */
	private function replaceCardPolicyRoles(int $policyId, string $action, array $roleIds): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_card_policy_roles')
			->where($qb->expr()->eq('policy_id', $qb->createNamedParameter($policyId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();

		foreach (array_values(array_unique($roleIds)) as $roleId) {
			if (!is_int($roleId) || $roleId <= 0) {
				continue;
			}
			$row = new CardPolicyRole();
			$row->setPolicyId($policyId);
			$row->setAction($action);
			$row->setRoleId($roleId);
			$this->cardPolicyRoleMapper->insert($row);
		}
	}

	private function deleteCardPolicyRoles(int $policyId): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete('deck_card_policy_roles')
			->where($qb->expr()->eq('policy_id', $qb->createNamedParameter($policyId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @param string[] $roleKeys
	 * @return int[]
	 */
	private function resolveRoleIdsByKeys(int $boardId, array $roleKeys): array
	{
		$roles = $this->roleMapper->findByBoard($boardId);
		$map = [];
		foreach ($roles as $role) {
			$map[(string) $role->getRoleKey()] = (int) $role->getId();
		}

		$out = [];
		foreach ($roleKeys as $key) {
			$key = (string) $key;
			if ($key === '' || !isset($map[$key])) {
				continue;
			}
			$out[] = (int) $map[$key];
		}
		return array_values(array_unique(array_filter($out, static fn (int $id) => $id > 0)));
	}

	private function findSettingOrNull(int $boardId): ?BoardPolicySetting
	{
		try {
			return $this->settingMapper->findByBoardId($boardId);
		} catch (\Throwable $e) {
			return null;
		}
	}
}
