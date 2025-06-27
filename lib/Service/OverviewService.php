<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\Board;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Model\CardDetails;
use OCP\Comments\ICommentsManager;
use OCP\IUserManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Security\Exceptions\ForbiddenException;

class OverviewService {
	private CardService $cardService;
	private BoardMapper $boardMapper;
	private LabelMapper $labelMapper;
	private CardMapper $cardMapper;
	private AssignmentMapper $assignedUsersMapper;
	private IUserManager $userManager;
	private ICommentsManager $commentsManager;
	private AttachmentService $attachmentService;

	public function __construct(
		CardService $cardService,
		BoardMapper $boardMapper,
		LabelMapper $labelMapper,
		CardMapper $cardMapper,
		AssignmentMapper $assignedUsersMapper,
		IUserManager $userManager,
		ICommentsManager $commentsManager,
		AttachmentService $attachmentService,
		BoardService $boardService
	) {
		$this->cardService = $cardService;
		$this->boardMapper = $boardMapper;
		$this->labelMapper = $labelMapper;
		$this->cardMapper = $cardMapper;
		$this->assignedUsersMapper = $assignedUsersMapper;
		$this->userManager = $userManager;
		$this->commentsManager = $commentsManager;
		$this->attachmentService = $attachmentService;
		$this->boardService = $boardService;
	}

	public function findUpcomingCards(string $userId, int $boardId = null, bool $done = true): array {

		$foundCards = [];

		if($boardId !== null) {
			try {
                $board = $this->boardService->find($boardId);
            } catch (DoesNotExistException | ForbiddenException $e) {
                return [];
            }

			if ($board->getOwner() === $userId) {
				$foundCards = $this->cardMapper->findAllByBoardId(
					$boardId, null, null, $done
				);
			} else {
				$foundCards = $this->cardMapper->findToMe(
					[$boardId], $userId, $done
				);
			}

		} else {
			$ownedBoardIds = [];
            $sharedBoardIds = [];
            $allCards = [];

			$userBoards = $this->boardMapper->findAllForUser($userId);
			foreach ($userBoards as $board) {
                if ($board->getOwner() === $userId) {
                    $ownedBoardIds[] = $board->getId();
                } else {
                    $sharedBoardIds[] = $board->getId();
                }
            }

			 if (!empty($ownedBoardIds)) {
                $allCards = array_merge(
					$allCards, 
					$this->cardMapper->findAllByBoardsId(
						$ownedBoardIds, null, null, $done
					)
				);
            }

			if (!empty($sharedBoardIds)) {
                $allCards = array_merge(
					$allCards, 
					$this->cardMapper->findToMe(
						$sharedBoardIds, 
						$userId, 
						$done
					)
				);
            }

			$foundCards = $allCards;
		}

		$this->cardService->enrichCards($foundCards);
		$overview = [];
		foreach ($foundCards as $card) {
			$diffDays = $card->getDaysUntilDue();

			$key = 'later';
			if ($diffDays === null) {
				$key = 'nodue';
			} elseif ($diffDays < 0) {
				$key = 'overdue';
			} elseif ($diffDays === 0) {
				$key = 'today';
			} elseif ($diffDays === 1) {
				$key = 'tomorrow';
			} elseif ($diffDays <= 7) {
				$key = 'nextSevenDays';
			}

			$card = (new CardDetails($card, $card->getRelatedBoard()));
			$overview[$key][] = $card->jsonSerialize();
		}
		
		return $overview;
	}
}
