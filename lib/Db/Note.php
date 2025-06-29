<?php

declare(strict_types=1);

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;
use OCP\IDb;

class Note extends Entity implements \JsonSerializable {
    public ?string $userId = null;
    public ?int $cardId = null;
    public string $content = '';
    public ?\DateTime $createdAt = null;
    public ?\DateTime $updatedAt = null;

    public function __construct() {
        $this->addType('cardId', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'cardId' => $this->cardId,
            'content' => $this->content,
            'createdAt' => $this->createdAt->format(\DateTime::ATOM),
            'updatedAt' => $this->updatedAt->format(\DateTime::ATOM),
        ];
    }
}