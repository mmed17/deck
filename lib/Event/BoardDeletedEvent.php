<?php

namespace OCA\Deck\Event;

use OCP\EventDispatcher\Event;

class BoardDeletedEvent extends Event {
    private int $boardId;

    public function __construct(int $boardId) {
        parent::__construct();
        $this->boardId = $boardId;
    }

    public function getBoard(): int {
        return $this->boardId;
    }
}