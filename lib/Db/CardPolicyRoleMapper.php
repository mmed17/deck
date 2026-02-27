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
 * @template-extends QBMapper<CardPolicyRole>
 */
class CardPolicyRoleMapper extends QBMapper
{
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'deck_card_policy_roles', CardPolicyRole::class);
	}

	/**
	 * @return CardPolicyRole[]
	 */
	public function findByPolicy(int $policyId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('policy_id', $qb->createNamedParameter($policyId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * @return CardPolicyRole[]
	 */
	public function findByPolicyAndAction(int $policyId, string $action): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('policy_id', $qb->createNamedParameter($policyId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('action', $qb->createNamedParameter($action, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}
}
