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
 * @template-extends QBMapper<StackTransitionPermission>
 */
class StackTransitionPermissionMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'deck_stack_transition_permissions', StackTransitionPermission::class);
    }

    /**
     * Find all transition permissions for a board
     *
     * @param int $boardId
     * @return StackTransitionPermission[]
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
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function find(int $id): StackTransitionPermission
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * Find permissions for a specific transition
     *
     * @param int $boardId
     * @param int|null $fromStackId
     * @param int $toStackId
     * @return StackTransitionPermission[]
     */
    public function findByTransition(int $boardId, ?int $fromStackId, int $toStackId): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('to_stack_id', $qb->createNamedParameter($toStackId, IQueryBuilder::PARAM_INT)));

        if ($fromStackId === null) {
            $qb->andWhere($qb->expr()->isNull('from_stack_id'));
        } else {
            // Find permissions for this specific transition OR from any stack (null)
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('from_stack_id', $qb->createNamedParameter($fromStackId, IQueryBuilder::PARAM_INT)),
                    $qb->expr()->isNull('from_stack_id')
                )
            );
        }

        return $this->findEntities($qb);
    }

    /**
     * Delete all permissions for a board
     *
     * @param int $boardId
     */
    public function deleteByBoard(int $boardId): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)));
        $qb->execute();
    }

    /**
     * Delete all permissions for a stack
     *
     * @param int $stackId
     */
    public function deleteByStack(int $stackId): void
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->eq('from_stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT)),
                    $qb->expr()->eq('to_stack_id', $qb->createNamedParameter($stackId, IQueryBuilder::PARAM_INT))
                )
            );
        $qb->execute();
    }
}