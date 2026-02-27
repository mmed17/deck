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
 * @template-extends QBMapper<BoardPolicyDefaultRole>
 */
class BoardPolicyDefaultRoleMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'deck_board_policy_default_roles', BoardPolicyDefaultRole::class);
	}

	/**
	 * @return BoardPolicyDefaultRole[]
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
	 * @return BoardPolicyDefaultRole[]
	 */
	public function findByBoardAndAction(int $boardId, string $action): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}
}
