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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IGroupManager;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class CardPolicyService
{
	public const ACTION_MOVE = BoardPolicyDefaultRole::ACTION_MOVE;
	public const ACTION_APPROVE = BoardPolicyDefaultRole::ACTION_APPROVE;

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
	 * @return array{move: string[], approve: string[]}
	 */
	public function getDefaultRoleKeysByAction(int $boardId): array
	{
		$defaults = $this->defaultRoleMapper->findByBoard($boardId);
		$roles = $this->roleMapper->findByBoard($boardId);
		$roleKeyById = [];
		foreach ($roles as $r) {
			$roleKeyById[(int) $r->getId()] = (string) $r->getRoleKey();
		}
		$out = ['move' => [], 'approve' => []];
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
		return $out;
	}

	/**
	 * Returns explicit per-card policies keyed by cardId.
	 *
	 * @return array<int, array{move: string[], approve: string[]}>
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
			if ($cardId <= 0 || ($action !== self::ACTION_MOVE && $action !== self::ACTION_APPROVE) || $roleKey === '') {
				continue;
			}
			if (!isset($out[$cardId])) {
				$out[$cardId] = ['move' => [], 'approve' => []];
			}
			$out[$cardId][$action][] = $roleKey;
		}
		$result->closeCursor();

		foreach ($out as $cardId => $actions) {
			$out[$cardId]['move'] = array_values(array_unique($actions['move'] ?? []));
			$out[$cardId]['approve'] = array_values(array_unique($actions['approve'] ?? []));
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
	 */
	public function setBoardDefaultRolesByKeys(int $boardId, array $moveRoleKeys, array $approveRoleKeys): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$this->ensureDefaultRolesAndDefaults($boardId);

		$moveRoleIds = $this->resolveRoleIdsByKeys($boardId, $moveRoleKeys);
		$approveRoleIds = $this->resolveRoleIdsByKeys($boardId, $approveRoleKeys);
		$this->replaceDefaultRoles($boardId, self::ACTION_MOVE, $moveRoleIds);
		$this->replaceDefaultRoles($boardId, self::ACTION_APPROVE, $approveRoleIds);
	}

	/**
	 * @param string[] $moveRoleKeys
	 * @param string[] $approveRoleKeys
	 */
	public function setCardPolicyByRoleKeys(int $boardId, int $cardId, array $moveRoleKeys, array $approveRoleKeys): void
	{
		$this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);
		$this->ensureDefaultRolesAndDefaults($boardId);

		$moveRoleIds = $this->resolveRoleIdsByKeys($boardId, $moveRoleKeys);
		$approveRoleIds = $this->resolveRoleIdsByKeys($boardId, $approveRoleKeys);

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
		return $this->membershipMapper->insert($membership);
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

		if ($this->permissionService->userIsBoardOwner($boardId, $userId)) {
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
