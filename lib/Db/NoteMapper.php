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
}