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
 * @template-extends QBMapper<BoardPolicyRole>
 */
class BoardPolicyRoleMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'deck_board_policy_roles', BoardPolicyRole::class);
	}

	/**
	 * @return BoardPolicyRole[]
	 */
	public function findByBoard(int $boardId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	public function findByBoardAndKey(int $boardId, string $roleKey): ?BoardPolicyRole
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('role_key', $qb->createNamedParameter($roleKey, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (\Throwable $e) {
			return null;
		}
	}
}
