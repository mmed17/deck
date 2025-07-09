<?php

namespace OCA\Deck\Event;

use OCA\Deck\Db\Board;
use OCP\EventDispatcher\Event;

class BoardCreatedEvent extends Event {
    private Board $board;

    public function __construct(Board $board) {
        parent::__construct();
        $this->board = $board;
    }

    public function getBoard(): Board {
        return $this->board;
    }
}