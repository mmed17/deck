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
 * @template-extends QBMapper<CardPolicy>
 */
class CardPolicyMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'deck_card_policy', CardPolicy::class);
	}

	public function findByBoardAndCard(int $boardId, int $cardId): ?CardPolicy
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (\Throwable $e) {
			return null;
		}
	}
}
