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
 * @template-extends QBMapper<CardPermissionTemplate>
 */
class CardPermissionTemplateMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'deck_perm_tpl', CardPermissionTemplate::class);
	}

	/**
	 * @return CardPermissionTemplate[]
	 */
	public function findByOrganization(int $organizationId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @return CardPermissionTemplate[]
	 */
	public function findByCreatedBy(string $createdBy): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('created_by', $qb->createNamedParameter($createdBy, IQueryBuilder::PARAM_STR)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function find(int $id): CardPermissionTemplate {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	public function findByOrganizationAndName(int $organizationId, string $name): ?CardPermissionTemplate {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (\Throwable $e) {
			return null;
		}
	}

	public function findByCreatedByAndName(string $createdBy, string $name): ?CardPermissionTemplate {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('created_by', $qb->createNamedParameter($createdBy, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (\Throwable $e) {
			return null;
		}
	}
}
