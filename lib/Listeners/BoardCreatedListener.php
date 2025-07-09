<?php

namespace OCA\Deck\Listeners;

use OCA\Deck\Event\BoardCreatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class BoardCreatedListener implements IEventListener {

    public function handle(Event $event): void {
        if (!($event instanceOf BoardCreatedEvent)) {
            return;
        }
    }
}