<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\BoardPolicyRoleMembership;
use OCA\Deck\Db\StackTransitionPermission;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AccountHandoverService {
	public function __construct(
		private IDBConnection $db,
		private BoardService $boardService,
		private AclMapper $aclMapper,
	) {
	}

	/**
	 * @return array{boardsTransferred:int, aclMembershipsAdded:int, assignedUsersRemapped:int, policyMembershipsRemapped:int, transitionPermissionsRemapped:int}
	 */
	public function handoverUserInOrganization(string $sourceUserId, string $targetUserId, int $organizationId, bool $remapContent = true): array {
		$sourceUserId = trim($sourceUserId);
		$targetUserId = trim($targetUserId);

		if ($sourceUserId === '') {
			throw new \InvalidArgumentException('sourceUserId must not be empty');
		}

		if ($targetUserId === '') {
			throw new \InvalidArgumentException('targetUserId must not be empty');
		}

		if ($sourceUserId === $targetUserId) {
			throw new \InvalidArgumentException('sourceUserId and targetUserId must differ');
		}

		if ($organizationId <= 0) {
			throw new \InvalidArgumentException('organizationId must be a positive integer');
		}

		$boardsTransferred = 0;
		$aclMembershipsAdded = 0;
		$assignedUsersRemapped = 0;
		$policyMembershipsRemapped = 0;
		$transitionPermissionsRemapped = 0;

		foreach ($this->findOrganizationBoards($organizationId) as $board) {
			$boardId = (int) $board['boardId'];
			$owner = (string) $board['owner'];
			$sourceUserAcl = $this->findDirectUserAcl($boardId, $sourceUserId);

			if ($owner === $sourceUserId) {
				$this->boardService->transferBoardOwnership($boardId, $targetUserId, $remapContent);
				$boardsTransferred++;
			}

			if ($sourceUserAcl !== null && $this->findDirectUserAcl($boardId, $targetUserId) === null) {
				if ($this->insertDirectUserAcl($boardId, $targetUserId, $sourceUserAcl)) {
					$aclMembershipsAdded++;
				}
			}

			$assignedUsersRemapped += $this->remapAssignedUsersForBoard($boardId, $sourceUserId, $targetUserId);
			$policyMembershipsRemapped += $this->remapPolicyRoleMembershipsForBoard($boardId, $sourceUserId, $targetUserId);
			$transitionPermissionsRemapped += $this->remapStackTransitionPermissionsForBoard($boardId, $sourceUserId, $targetUserId);
		}

		return [
			'boardsTransferred' => $boardsTransferred,
			'aclMembershipsAdded' => $aclMembershipsAdded,
			'assignedUsersRemapped' => $assignedUsersRemapped,
			'policyMembershipsRemapped' => $policyMembershipsRemapped,
			'transitionPermissionsRemapped' => $transitionPermissionsRemapped,
		];
	}

	private function remapAssignedUsersForBoard(int $boardId, string $sourceUserId, string $targetUserId): int {
		$remapped = 0;
		$this->db->beginTransaction();

		try {
			$select = $this->db->getQueryBuilder();
			$select->select('a.id', 'a.card_id')
				->from('deck_assigned_users', 'a')
				->innerJoin('a', 'deck_cards', 'c', $select->expr()->eq('a.card_id', 'c.id'))
				->innerJoin('c', 'deck_stacks', 's', $select->expr()->eq('c.stack_id', 's.id'))
				->where($select->expr()->eq('s.board_id', $select->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
				->andWhere($select->expr()->eq('a.type', $select->createNamedParameter(Assignment::TYPE_USER, IQueryBuilder::PARAM_INT)))
				->andWhere($select->expr()->eq('a.participant', $select->createNamedParameter($sourceUserId, IQueryBuilder::PARAM_STR)));

			$result = $select->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$rowId = (int) ($row['id'] ?? 0);
				$cardId = (int) ($row['card_id'] ?? 0);

				if ($rowId <= 0 || $cardId <= 0) {
					continue;
				}

				if ($this->assignedUserTargetExists($cardId, $targetUserId)) {
					$delete = $this->db->getQueryBuilder();
					$delete->delete('deck_assigned_users')
						->where($delete->expr()->eq('id', $delete->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)));

					$remapped += $delete->executeStatement();
					continue;
				}

				$update = $this->db->getQueryBuilder();
				$update->update('deck_assigned_users')
					->set('participant', $update->createNamedParameter($targetUserId, IQueryBuilder::PARAM_STR))
					->where($update->expr()->eq('id', $update->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)))
					->andWhere($update->expr()->eq('participant', $update->createNamedParameter($sourceUserId, IQueryBuilder::PARAM_STR)));

				$remapped += $update->executeStatement();
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $remapped;
	}

	private function assignedUserTargetExists(int $cardId, string $targetUserId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('deck_assigned_users')
			->where($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(Assignment::TYPE_USER, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($targetUserId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetch() !== false;
		$result->closeCursor();

		return $exists;
	}

	private function remapPolicyRoleMembershipsForBoard(int $boardId, string $sourceUserId, string $targetUserId): int {
		$remapped = 0;
		$this->db->beginTransaction();

		try {
			$select = $this->db->getQueryBuilder();
			$select->select('id', 'role_id')
				->from('deck_board_policy_role_memberships')
				->where($select->expr()->eq('board_id', $select->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
				->andWhere($select->expr()->eq('participant_type', $select->createNamedParameter(BoardPolicyRoleMembership::PARTICIPANT_TYPE_USER, IQueryBuilder::PARAM_INT)))
				->andWhere($select->expr()->eq('participant', $select->createNamedParameter($sourceUserId, IQueryBuilder::PARAM_STR)));

			$result = $select->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$rowId = (int) ($row['id'] ?? 0);
				$roleId = (int) ($row['role_id'] ?? 0);

				if ($rowId <= 0 || $roleId <= 0) {
					continue;
				}

				if ($this->policyMembershipTargetExists($boardId, $roleId, $targetUserId)) {
					$delete = $this->db->getQueryBuilder();
					$delete->delete('deck_board_policy_role_memberships')
						->where($delete->expr()->eq('id', $delete->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)));

					$remapped += $delete->executeStatement();
					continue;
				}

				$update = $this->db->getQueryBuilder();
				$update->update('deck_board_policy_role_memberships')
					->set('participant', $update->createNamedParameter($targetUserId, IQueryBuilder::PARAM_STR))
					->where($update->expr()->eq('id', $update->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)))
					->andWhere($update->expr()->eq('participant', $update->createNamedParameter($sourceUserId, IQueryBuilder::PARAM_STR)));

				$remapped += $update->executeStatement();
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $remapped;
	}

	private function policyMembershipTargetExists(int $boardId, int $roleId, string $targetUserId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('deck_board_policy_role_memberships')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('role_id', $qb->createNamedParameter($roleId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant_type', $qb->createNamedParameter(BoardPolicyRoleMembership::PARTICIPANT_TYPE_USER, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($targetUserId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$exists = $result->fetch() !== false;
		$result->closeCursor();

		return $exists;
	}

	private function remapStackTransitionPermissionsForBoard(int $boardId, string $sourceUserId, string $targetUserId): int {
		$remapped = 0;
		$this->db->beginTransaction();

		try {
			$select = $this->db->getQueryBuilder();
			$select->select('id', 'from_stack_id', 'to_stack_id', 'required_role')
				->from('deck_stack_transition_permissions')
				->where($select->expr()->eq('board_id', $select->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
				->andWhere($select->expr()->eq('participant_type', $select->createNamedParameter(StackTransitionPermission::PARTICIPANT_TYPE_USER, IQueryBuilder::PARAM_INT)))
				->andWhere($select->expr()->eq('participant', $select->createNamedParameter($sourceUserId, IQueryBuilder::PARAM_STR)));

			$result = $select->executeQuery();
			$rows = $result->fetchAll();
			$result->closeCursor();

			foreach ($rows as $row) {
				$rowId = (int) ($row['id'] ?? 0);
				$toStackId = (int) ($row['to_stack_id'] ?? 0);
				$requiredRole = (string) ($row['required_role'] ?? '');
				$fromStackId = $row['from_stack_id'] === null ? null : (int) $row['from_stack_id'];

				if ($rowId <= 0 || $toStackId <= 0 || $requiredRole === '') {
					continue;
				}

				if ($this->transitionPermissionTargetExists($boardId, $fromStackId, $toStackId, $requiredRole, $targetUserId)) {
					$delete = $this->db->getQueryBuilder();
					$delete->delete('deck_stack_transition_permissions')
						->where($delete->expr()->eq('id', $delete->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)));

					$remapped += $delete->executeStatement();
					continue;
				}

				$update = $this->db->getQueryBuilder();
				$update->update('deck_stack_transition_permissions')
					->set('participant', $update->createNamedParameter($targetUserId, IQueryBuilder::PARAM_STR))
					->where($update->expr()->eq('id', $update->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)))
					->andWhere($update->expr()->eq('participant', $update->createNamedParameter($sourceUserId, IQueryBuilder::PARAM_STR)));

				$remapped += $update->executeStatement();
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $remapped;
	}

	private function transitionPermissionTargetExists(int $boardId, ?int $fromStackId, int $toStackId, string $requiredRole, string $targetUserId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('deck_stack_transition_permissions')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('to_stack_id', $qb->createNamedParameter($toStackId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('required_role', $qb->createNamedParameter($requiredRole, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('participant_type', $qb->createNamedParameter(StackTransitionPermission::PARTICIPANT_TYPE_USER, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($targetUserId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		if ($fromStackId === null) {
			$qb->andWhere($qb->expr()->isNull('from_stack_id'));
		} else {
			$qb->andWhere($qb->expr()->eq('from_stack_id', $qb->createNamedParameter($fromStackId, IQueryBuilder::PARAM_INT)));
		}

		$result = $qb->executeQuery();
		$exists = $result->fetch() !== false;
		$result->closeCursor();

		return $exists;
	}

	/**
	 * @return array<int, array{boardId:int, owner:string}>
	 */
	private function findOrganizationBoards(int $organizationId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('b.id', 'b.owner')
			->from('custom_projects', 'cp')
			->innerJoin('cp', 'deck_boards', 'b', $qb->expr()->eq('cp.board_id', 'b.id'))
			->where($qb->expr()->eq('cp.organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$boardsById = [];
		foreach ($rows as $row) {
			$boardId = isset($row['id']) ? (int) $row['id'] : 0;
			if ($boardId <= 0) {
				continue;
			}
			$boardsById[$boardId] = [
				'boardId' => $boardId,
				'owner' => isset($row['owner']) ? (string) $row['owner'] : '',
			];
		}

		return array_values($boardsById);
	}

	/**
	 * @return array{permissionEdit:bool,permissionShare:bool,permissionManage:bool}|null
	 */
	private function findDirectUserAcl(int $boardId, string $userId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('permission_edit', 'permission_share', 'permission_manage')
			->from('deck_board_acl')
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(Acl::PERMISSION_TYPE_USER, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('participant', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			return null;
		}

		return [
			'permissionEdit' => (int) ($row['permission_edit'] ?? 0) === 1,
			'permissionShare' => (int) ($row['permission_share'] ?? 0) === 1,
			'permissionManage' => (int) ($row['permission_manage'] ?? 0) === 1,
		];
	}

	/**
	 * @param array{permissionEdit:bool,permissionShare:bool,permissionManage:bool} $permissions
	 */
	private function insertDirectUserAcl(int $boardId, string $userId, array $permissions): bool {
		$acl = new Acl();
		$acl->setBoardId($boardId);
		$acl->setType(Acl::PERMISSION_TYPE_USER);
		$acl->setParticipant($userId);
		$acl->setPermissionEdit($permissions['permissionEdit']);
		$acl->setPermissionShare($permissions['permissionShare']);
		$acl->setPermissionManage($permissions['permissionManage']);

		try {
			$this->aclMapper->insert($acl);
			return true;
		} catch (DbException $e) {
			if ($e->getReason() === DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				return false;
			}
			throw $e;
		}
	}
}
