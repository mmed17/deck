<?php

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Mapper for the custom project data.
 *
 * This class extends DeckMapper for consistency but does NOT implement IPermissionMapper,
 * as project visibility is implicitly handled by board permissions.
 */
class ProjectMapper extends DeckMapper {
    public const TABLE_NAME = 'custom_projects';

    public function __construct(IDBConnection $db) {
        parent::__construct($db, self::TABLE_NAME, Project::class);
    }

    /**
     * Finds a project by its associated board_id.
     *
     * @param int $boardId The ID of the board.
     * @return Project|null The found project entity or null if not found.
     */
    public function findByBoardId(int $boardId): ?Project {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName()) // Use getTableName() to respect the parent class
           ->where(
               $qb->expr()->eq('board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT))
           );

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
}