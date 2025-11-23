<?php

declare(strict_types=1);

namespace OCA\Deck\Db;

use DateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Db\Entity;

class NoteMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'private_card_notes', Note::class);
    }

    /**
     * Find a single note by its ID
     */
    public function find(int $id): Note {
        $qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, 
            IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * Get all notes for a given user and card ID, with pagination
     *
     * @return Note[]
     */
    public function findByCard(string $userId, int $cardId, int $limit = 10, int $offset = 0): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->andWhere($qb->expr()->eq('card_id', $qb->createNamedParameter($cardId, IQueryBuilder::PARAM_INT)))
            ->orderBy('updated_at', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $this->findEntities($qb);
    }

    /**
     * Create a new note in the database
     */
    public function create(string $userId, int $cardId, string $content): Note {
        $note = new Note();
        $now = new DateTime();

        $note->setUserId($userId);
        $note->setCardId($cardId);
        $note->setContent($content);
        $note->setCreatedAt($now);
        $note->setUpdatedAt($now);

        return $this->insert($note);
    }

    /**
     * Update an existing note
     */
    public function update(Entity $note): Entity {
        $now = new DateTime();      
        $note->setUpdatedAt($now);
        return parent::update($note);
    }

    /**
     * Delete a note
     */
    public function delete(Entity $note): Entity {
        return parent::delete($note);
    }

    /**
     * Get the latest notes for a specific BOARD and USER.
     * Joins the notes with cards and stacks to filter by board_id.
     * * @param int $boardId The Deck Board ID
     * @param string $userId The current User ID
     * @param int $limit Number of notes to return (default 5)
     * @return Note[]
     */
    public function findLatestByBoard(int $boardId, string $userId, int $limit = 5): array {
        $qb = $this->db->getQueryBuilder();
        
        $qb->select('n.*')
            ->from($this->getTableName(), 'n')
            // Join Cards table to link note -> card
            ->innerJoin('n', 'deck_cards', 'c', 'n.card_id = c.id')
            // Join Stacks table to link card -> board
            ->innerJoin('c', 'deck_stacks', 's', 'c.stack_id = s.id')
            // Filter by Board ID
            ->where($qb->expr()->eq('s.board_id', $qb->createNamedParameter($boardId, IQueryBuilder::PARAM_INT)))
            // Filter by User ID
            ->andWhere($qb->expr()->eq('n.user_id', $qb->createNamedParameter($userId)))
            // Get the newest ones
            ->orderBy('n.updated_at', 'DESC')
            ->setMaxResults($limit);

        return $this->findEntities($qb);
    }
}