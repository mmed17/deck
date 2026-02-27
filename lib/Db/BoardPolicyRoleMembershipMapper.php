<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<BoardPolicyRoleMembership>
 */
class BoardPolicyRoleMembershipMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'deck_board_policy_role_memberships', BoardPolicyRoleMembership::class);
	}

	/**
	 * @return BoardPolicyRoleMembership[]
	 */
	public function findByBoard(int $boardId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @param int[] $roleIds
	 * @return BoardPolicyRoleMembership[]
	 */
	public function findByBoardAndRoleIds(int $boardId, array $roleIds): array
	{
		if ($roleIds === []) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('role_id', $qb->createNamedParameter($roleIds, IQueryBuilder::PARAM_INT_ARRAY)));
		return $this->findEntities($qb);
	}
}
