<?php

/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\Activity\ChangeSet;
use OCA\Deck\BadRequestException;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Assignment;
use OCA\Deck\Db\AssignmentMapper;
use OCA\Deck\Db\BoardMapper;
use OCA\Deck\Db\Card;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\Db\Label;
use OCA\Deck\Db\LabelMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Event\CardCreatedEvent;
use OCA\Deck\Event\CardDeletedEvent;
use OCA\Deck\Event\CardUpdatedEvent;
use OCA\Deck\Model\CardDetails;
use OCA\Deck\Model\OptionalNullableValue;
use OCA\Deck\NoPermissionException;
use OCA\Deck\Notification\NotificationHelper;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\CardServiceValidator;
use OCP\Collaboration\Reference\IReferenceManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCA\ProjectCreatorAIO\Db\Project;
use OCA\ProjectCreatorAIO\Service\ProjectDeckActivityService;
use OCA\Deck\Service\CardPolicyService;

class CardService
{
	public function __construct(
		private CardMapper $cardMapper,
		private StackMapper $stackMapper,
		private BoardMapper $boardMapper,
		private LabelMapper $labelMapper,
		private LabelService $labelService,
		private PermissionService $permissionService,
		private BoardService $boardService,
		private NotificationHelper $notificationHelper,
		private AssignmentMapper $assignedUsersMapper,
		private AttachmentService $attachmentService,
		private ActivityManager $activityManager,
		private ICommentsManager $commentsManager,
		private IUserManager $userManager,
		private ChangeHelper $changeHelper,
		private IEventDispatcher $eventDispatcher,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		private IRequest $request,
		private CardServiceValidator $cardServiceValidator,
		private AssignmentService $assignmentService,
		private IReferenceManager $referenceManager,
		private ProjectMapper $projectMapper,
		private ProjectDeckActivityService $projectDeckActivityService,
		private CardPolicyService $cardPolicyService,
		private ?string $userId,
	) {
	}

	public function enrichCards($cards)
	{
		$user = $this->userManager->get($this->userId);

		$cardIds = array_map(function (Card $card) use ($user) {
			// Everything done in here might be heavy as it is executed for every card
			$cardId = $card->getId();
			$this->cardMapper->mapOwner($card);

			$card->setAttachmentCount($this->attachmentService->count($cardId));

			// TODO We should find a better way just to get the comment count so we can save 1-3 queries per card here
			$countComments = $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string) $card->getId());
			$lastRead = $countComments > 0 ? $this->commentsManager->getReadMark('deckCard', (string) $card->getId(), $user) : null;
			$countUnreadComments = $lastRead ? $this->commentsManager->getNumberOfCommentsForObject('deckCard', (string) $card->getId(), $lastRead) : 0;
			$card->setCommentsUnread($countUnreadComments);
			$card->setCommentsCount($countComments);

			$stack = $this->stackMapper->find($card->getStackId());
			$board = $this->boardService->find($stack->getBoardId(), false);
			$card->setRelatedStack($stack);
			$card->setRelatedBoard($board);

			$project = $this->projectMapper->findByBoardId($board->getId());
			if ($project !== null) {
				$card->setProject($project);
			}
			return $card->getId();
		}, $cards);

		$assignedLabels = $this->labelMapper->findAssignedLabelsForCards($cardIds);
		$assignedUsers = $this->assignedUsersMapper->findIn($cardIds);

		foreach ($cards as $card) {
			$cardLabels = array_values(array_filter($assignedLabels, function (Label $label) use ($card) {
				return $label->getCardId() === $card->getId();
			}));
			$cardAssignedUsers = array_values(array_filter($assignedUsers, function (Assignment $assignment) use ($card) {
				return $assignment->getCardId() === $card->getId();
			}));
			$card->setLabels($cardLabels);
			$card->setAssignedUsers($cardAssignedUsers);
		}

		return array_map(
			function (Card $card): CardDetails {
				$cardDetails = new CardDetails($card);

				$references = $this->referenceManager->extractReferences($card->getTitle());
				$reference = array_shift($references);
				if ($reference) {
					$referenceData = $this->referenceManager->resolveReference($reference);
					$cardDetails->setReferenceData($referenceData);
				}

				if ($card->getProject() !== null) {
					$cardDetails->setProject($card->getProject());
				}

				return $cardDetails;
			},
			$cards
		);
	}

	public function fetchDeleted($boardId)
	{
		$this->cardServiceValidator->check(compact('boardId'));
		$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		$cards = $this->cardMapper->findDeleted($boardId);
		$this->enrichCards($cards);
		return $cards;
	}

	/**
	 * @return \OCA\Deck\Db\RelationalEntity
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function find(int $cardId)
	{
		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		$boardIdForPolicy = (int) ($this->cardMapper->findBoardId($cardId) ?? 0);
		if ($boardIdForPolicy > 0) {
			$this->cardPolicyService->assertUserAllowedToViewCard($boardIdForPolicy, $cardId, $this->userId);
		}
		$card = $this->cardMapper->find($cardId);
		[$card] = $this->enrichCards([$card]);

		// Attachments are only enriched on individual card fetching
		$attachments = $this->attachmentService->findAll($cardId, true);
		if ($this->request->getParam('apiVersion') === '1.0') {
			$attachments = array_filter($attachments, function ($attachment) {
				return $attachment->getType() === 'deck_file';
			});
		}
		$card->setAttachments($attachments);

		return $card;
	}

	public function findCalendarEntries($boardId)
	{
		try {
			$this->permissionService->checkPermission($this->boardMapper, $boardId, Acl::PERMISSION_READ);
		} catch (NoPermissionException $e) {
			$this->logger->error('Unable to check permission for a previously obtained board ' . $boardId, ['exception' => $e]);
			return [];
		}
		$cards = $this->cardMapper->findCalendarEntries($boardId);
		$this->enrichCards($cards);
		return $cards;
	}

	/**
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param integer $order
	 * @param $description
	 * @param $owner
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadrequestException
	 */
	public function create($title, $stackId, $type, $order, $owner, $description = '', $duedate = null)
	{
		$this->cardServiceValidator->check(compact('title', 'stackId', 'type', 'order', 'owner'));

		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->stackMapper, $stackId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = new Card();
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setOwner($owner);
		$card->setDescription($description);
		$card->setDuedate($duedate);
		$card = $this->cardMapper->insert($card);

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_CREATE);
		$this->changeHelper->cardChanged($card->getId(), false);
		$this->eventDispatcher->dispatchTyped(new CardCreatedEvent($card));

		[$card] = $this->enrichCards([$card]);

		return $card;
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function delete($id)
	{
		if (is_numeric($id) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setDeletedAt(time());
		$this->cardMapper->update($card);

		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_DELETE);
		$this->notificationHelper->markDuedateAsRead($card);
		$this->changeHelper->cardChanged($card->getId(), false);
		$this->eventDispatcher->dispatchTyped(new CardDeletedEvent($card));

		return $card;
	}

	/**
	 * @param $id
	 * @param $title
	 * @param $stackId
	 * @param $type
	 * @param $owner
	 * @param $description
	 * @param $order
	 * @param $duedate
	 * @param $deletedAt
	 * @param $archived
	 * @param $done
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function update($id, $title, $stackId, $type, $owner, $description = '', $order = 0, $duedate = null, $deletedAt = null, $archived = null, ?OptionalNullableValue $done = null)
	{
		$this->cardServiceValidator->check(compact('id', 'title', 'stackId', 'type', 'owner', 'order'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT, allowDeletedCard: true);
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);

		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$beforeStackId = (int) $card->getStackId();
		$toStackId = (int) $stackId;
		$boardIdForPolicy = (int) ($this->cardMapper->findBoardId((int) $id) ?? 0);
		$isProjectLinkedBoard = $this->isProjectLinkedBoard($boardIdForPolicy);
		$policyEnabled = $boardIdForPolicy > 0 && $this->cardPolicyService->isCardPolicyEnabled($boardIdForPolicy);
		$approvedStackId = $policyEnabled ? (int) ($this->cardPolicyService->getApprovedStackId($boardIdForPolicy) ?? 0) : 0;
		$doneStackId = $policyEnabled ? (int) ($this->cardPolicyService->getDoneStackId($boardIdForPolicy) ?? 0) : 0;
		$actorId = (string) ($this->userId ?? '');
		$triggerDoneActivity = false;
		$triggerUndoneActivity = false;
		if ($archived !== null && $card->getArchived() && $archived === true) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}

		if ($card->getDeletedAt() !== 0) {
			if ($deletedAt === null || $deletedAt > 0) {
				// Only allow operations when restoring the card
				throw new NoPermissionException('Operation not allowed. This card was deleted.');
			}
		}

		$changes = new ChangeSet($card);
		if ($card->getLastEditor() !== $this->userId && $card->getLastEditor() !== null) {
			$this->activityManager->triggerEvent(
				ActivityManager::DECK_OBJECT_CARD,
				$card,
				ActivityManager::SUBJECT_CARD_UPDATE_DESCRIPTION,
				[
					'before' => $card->getDescriptionPrev(),
					'after' => $card->getDescription()
				],
				$card->getLastEditor()
			);

			$card->setDescriptionPrev($card->getDescription());
			$card->setLastEditor($this->userId);
		}
		$card->setTitle($title);
		$card->setStackId($stackId);
		$card->setType($type);
		$card->setOrder($order);
		$card->setOwner($owner);
		$card->setDuedate($duedate ? new \DateTime($duedate) : null);
		$resetDuedateNotification = false;
		if (
			$card->getDuedate() === null ||
			($card->getDuedate()) != ($changes->getBefore()->getDuedate())
		) {
			$card->setNotified(false);
			$resetDuedateNotification = true;
		}

		if ($deletedAt !== null) {
			$card->setDeletedAt($deletedAt);
		}
		if ($archived !== null) {
			$card->setArchived($archived);
		}
		if ($done !== null && $isProjectLinkedBoard) {
			if ($this->isExplicitDoneChange($card, $done)) {
				throw new StatusException('Operation not allowed. On project-linked boards, move the card between stacks to change done state.');
			}
			// Ignore unchanged done values from generic update payloads.
			$done = null;
		}
		if ($policyEnabled) {
			// Keep done state in sync with Done transitions
			if ($beforeStackId !== $toStackId && $doneStackId > 0) {
				if ($toStackId === $doneStackId && $card->getDone() === null) {
					$card->setDone(new \DateTime());
					$triggerDoneActivity = true;
				}
				if ($beforeStackId === $doneStackId && $toStackId !== $doneStackId && $card->getDone() !== null) {
					$card->setDone(null);
					$triggerUndoneActivity = true;
				}
			}

			// Explicit done changes: verify-only and only in Done
			if ($done !== null) {
				if ($actorId === '' || $doneStackId <= 0) {
					throw new NoPermissionException('Operation not allowed.');
				}
				$this->cardPolicyService->assertUserAllowedForAction($boardIdForPolicy, (int) $id, CardPolicyService::ACTION_VERIFY, $actorId);
				if ((int) $card->getStackId() !== $doneStackId) {
					throw new StatusException('Operation not allowed. Move the card to Done to change done state.');
				}
				$card->setDone($done->getValue());
			}
		} else {
			// Legacy behavior: only update done when explicitly provided
			if ($done !== null) {
				$card->setDone($done->getValue());
			}
		}


		// Trigger update events before setting description as it is handled separately
		$changes->setAfter($card);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_CARD, $changes, ActivityManager::SUBJECT_CARD_UPDATE);

		if ($card->getDescriptionPrev() === null) {
			$card->setDescriptionPrev($card->getDescription());
		}
		$card->setDescription($description);

		// @var Card $card
		$card = $this->cardMapper->update($card);
		if ($triggerDoneActivity) {
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE_DONE);
		}
		if ($triggerUndoneActivity) {
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE_UNDONE);
		}
		$oldBoardId = $this->stackMapper->findBoardId($changes->getBefore()->getStackId());
		$boardId = $this->cardMapper->findBoardId($card->getId());
		if ($boardId !== $oldBoardId) {
			$stack = $this->stackMapper->find($card->getStackId());
			$board = $this->boardService->find($this->cardMapper->findBoardId($card->getId()));
			$boardLabels = $board->getLabels() ?? [];
			foreach ($card->getLabels() as $cardLabel) {
				$this->removeLabel($card->getId(), $cardLabel->getId());
				$label = $this->labelMapper->find($cardLabel->getId());
				$filteredLabels = array_values(array_filter($boardLabels, fn($item) => $item->getTitle() === $label->getTitle()));
				// clone labels that are assigned to card but don't exist in new board
				if (empty($filteredLabels)) {
					if ($this->permissionService->getPermissions($boardId)[Acl::PERMISSION_MANAGE] === true) {
						$newLabel = $this->labelService->create($label->getTitle(), $label->getColor(), $board->getId());
						$boardLabels[] = $label;
						$this->assignLabel($card->getId(), $newLabel->getId());
					}
				} else {
					$this->assignLabel($card->getId(), $filteredLabels[0]->getId());
				}
			}
			$board->setLabels($boardLabels);
			$this->boardMapper->update($board);
			$this->changeHelper->boardChanged($board->getId());
		}

		$this->notifyCardMovedIfNeeded($card, $beforeStackId, $toStackId);

		if ($resetDuedateNotification) {
			$this->notificationHelper->markDuedateAsRead($card);
		}
		$this->changeHelper->cardChanged($card->getId(), true);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card, $changes->getBefore()));

		[$card] = $this->enrichCards([$card]);

		return $card;
	}

	public function cloneCard(int $id, ?int $targetStackId = null): Card
	{
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_READ);
		$originCard = $this->cardMapper->find($id);
		if ($targetStackId === null) {
			$targetStackId = $originCard->getStackId();
		}
		$this->permissionService->checkPermission($this->stackMapper, $targetStackId, Acl::PERMISSION_EDIT);
		$newCard = $this->create($originCard->getTitle(), $targetStackId, $originCard->getType(), $originCard->getOrder(), $originCard->getOwner());
		$boardId = $this->stackMapper->findBoardId($targetStackId);
		foreach ($this->labelMapper->findAssignedLabelsForCard($id) as $label) {
			if ($boardId != $this->stackMapper->findBoardId($originCard->getStackId())) {
				try {
					$label = $this->labelService->cloneLabelIfNotExists($label->getId(), $boardId);
				} catch (NoPermissionException $e) {
					break;
				}
			}
			$this->assignLabel($newCard->getId(), $label->getId());
		}
		foreach ($this->assignedUsersMapper->findAll($id) as $assignement) {
			try {
				$this->permissionService->checkPermission($this->cardMapper, $newCard->getId(), Acl::PERMISSION_READ, $assignement->getParticipant());
			} catch (NoPermissionException $e) {
				continue;
			}
			$this->assignmentService->assignUser($newCard->getId(), $assignement->getParticipant());
		}
		$newCard->setDescription($originCard->getDescription());
		$card = $this->enrichCards([$this->cardMapper->update($newCard)]);
		return $card[0];
	}

	/**
	 * @param $id
	 * @param $title
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function rename($id, $title)
	{
		$this->cardServiceValidator->check(compact('id', 'title'));

		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->isCombiProjectBoardByCardId((int) $id)) {
			throw new StatusException('Operation not allowed. Cards are fixed for combi project boards.');
		}
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}

		$changes = new ChangeSet($card);
		$card->setTitle($title);
		$this->changeHelper->cardChanged($card->getId(), false);
		$update = $this->cardMapper->update($card);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $update;
	}

	/**
	 * @param $id
	 * @param $stackId
	 * @param $order
	 * @return array
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function reorder($id, $stackId, $order)
	{
		$this->cardServiceValidator->check(compact('id', 'stackId', 'order'));


		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->stackMapper, $stackId, Acl::PERMISSION_EDIT);

		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}

		$card = $this->cardMapper->find($id);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}

		$changes = new ChangeSet($card);

			$oldStackId = (int) $card->getStackId();
			$boardId = (int) ($this->cardMapper->findBoardId((int) $id) ?? 0);
			$policyEnabled = $boardId > 0 && $this->cardPolicyService->isCardPolicyEnabled($boardId);
			$approvedStackId = $policyEnabled ? (int) ($this->cardPolicyService->getApprovedStackId($boardId) ?? 0) : 0;
			$doneStackId = $policyEnabled ? (int) ($this->cardPolicyService->getDoneStackId($boardId) ?? 0) : 0;
			$actorId = (string) ($this->userId ?? '');

			$triggerDoneActivity = false;
			$triggerUndoneActivity = false;
			if ($policyEnabled && $oldStackId !== (int) $stackId) {
				if ($actorId === '') {
					throw new NoPermissionException('Operation not allowed.');
				}
				$requiresSign = $approvedStackId > 0
					&& ($oldStackId === $approvedStackId || (int) $stackId === $approvedStackId);
				$requiresVerify = $doneStackId > 0
					&& ($oldStackId === $doneStackId || (int) $stackId === $doneStackId);
				if (($requiresSign && $approvedStackId <= 0) || ($requiresVerify && $doneStackId <= 0)) {
					throw new NoPermissionException('Operation not allowed.');
				}
				$this->cardPolicyService->assertUserAllowedForAction(
					$boardId,
					(int) $id,
					$requiresVerify
						? CardPolicyService::ACTION_VERIFY
						: ($requiresSign ? CardPolicyService::ACTION_SIGN : CardPolicyService::ACTION_MOVE),
					$actorId,
				);

				// Auto-sync done based on Done transitions
				if ($doneStackId > 0 && (int) $stackId === $doneStackId && $card->getDone() === null) {
					$card->setDone(new \DateTime());
					$triggerDoneActivity = true;
				}
				if ($doneStackId > 0 && $oldStackId === $doneStackId && (int) $stackId !== $doneStackId && $card->getDone() !== null) {
					$card->setDone(null);
					$triggerUndoneActivity = true;
				}
		}

		$card->setStackId($stackId);
		$card = $this->cardMapper->update($card);
		if ($triggerDoneActivity) {
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE_DONE);
		}
		if ($triggerUndoneActivity) {
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE_UNDONE);
		}
		$changes->setAfter($card);
		$this->activityManager->triggerUpdateEvents(ActivityManager::DECK_OBJECT_CARD, $changes, ActivityManager::SUBJECT_CARD_UPDATE);
		$this->notifyCardMovedIfNeeded($card, $oldStackId, (int) $stackId);

		$cards = $this->cardMapper->findAll($stackId);
		$result = [];
		$i = 0;
		foreach ($cards as $card) {
			if ($card->getArchived()) {
				throw new StatusException('Operation not allowed. This card is archived.');
			}
			if ($card->id === $id) {
				$card->setOrder($order);
				$card->setLastModified(time());
			}

			if ($i === $order) {
				$i++;
			}

			if ($card->id !== $id) {
				$card->setOrder($i++);
			}
			$this->cardMapper->update($card);
			$result[$card->getOrder()] = $card;
		}
		$this->changeHelper->cardChanged($id, false);
		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return array_values($result);
	}

	private function notifyCardMovedIfNeeded(Card $card, int $beforeStackId, int $afterStackId): void
	{
		if ($beforeStackId <= 0 || $afterStackId <= 0 || $beforeStackId === $afterStackId) {
			return;
		}

		$beforeStack = $this->stackMapper->find($beforeStackId);
		$currentStack = $this->stackMapper->find($afterStackId);
		$this->notificationHelper->sendCardMoved(
			$card,
			(string) $beforeStack->getTitle(),
			(string) $currentStack->getTitle(),
		);
		$this->projectDeckActivityService->recordCardMoveByBoardId((int) $currentStack->getBoardId());
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function archive($id)
	{
		$this->cardServiceValidator->check(compact('id'));


		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(true);
		$newCard = $this->cardMapper->update($card);
		$this->notificationHelper->markDuedateAsRead($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_ARCHIVE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $newCard;
	}

	/**
	 * @param $id
	 * @return \OCP\AppFramework\Db\Entity
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function unarchive($id)
	{
		$this->cardServiceValidator->check(compact('id'));


		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$card->setArchived(false);
		$newCard = $this->cardMapper->update($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_UNARCHIVE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $newCard;
	}

	/**
	 * @param $id
	 * @return \OCA\Deck\Db\Card
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function done(int $id): Card
	{
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$boardId = (int) ($this->cardMapper->findBoardId($id) ?? 0);
		if ($this->isProjectLinkedBoard($boardId)) {
			throw new StatusException('Operation not allowed. On project-linked boards, move the card to the Done stack to mark it as done.');
		}
		$actorId = (string) ($this->userId ?? '');
		$movedIntoDone = false;

		if ($boardId > 0 && $this->cardPolicyService->isCardPolicyEnabled($boardId)) {
			$doneStackId = (int) ($this->cardPolicyService->getDoneStackId($boardId) ?? 0);
			if ($actorId === '' || $doneStackId <= 0) {
				throw new NoPermissionException('Operation not allowed.');
			}
			$this->cardPolicyService->assertUserAllowedForAction($boardId, $id, CardPolicyService::ACTION_VERIFY, $actorId);
			if ((int) $card->getStackId() !== $doneStackId) {
				$targetOrder = count($this->cardMapper->findAll($doneStackId));
				$this->reorder($id, $doneStackId, $targetOrder);
				$movedIntoDone = true;
				$card = $this->cardMapper->find($id);
			}
		}

		if ($card->getDone() === null) {
			$card->setDone(new \DateTime());
			$card = $this->cardMapper->update($card);
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE_DONE);
		} elseif (!$movedIntoDone) {
			// Keep legacy behavior: still emit done activity for explicit done calls
			$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_CARD_UPDATE_DONE);
		}

		$this->notificationHelper->markDuedateAsRead($card);
		$this->changeHelper->cardChanged($id, false);
		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));
		return $card;
	}

	/**
	 * @param $id
	 * @return \OCA\Deck\Db\Card
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function undone(int $id): Card
	{
		$this->permissionService->checkPermission($this->cardMapper, $id, Acl::PERMISSION_EDIT);
		if ($this->boardService->isArchived($this->cardMapper, $id)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($id);
		$boardId = (int) ($this->cardMapper->findBoardId($id) ?? 0);
		if ($this->isProjectLinkedBoard($boardId)) {
			throw new StatusException('Operation not allowed. On project-linked boards, move the card out of the Done stack to mark it as not done.');
		}
		$actorId = (string) ($this->userId ?? '');
		if ($boardId > 0 && $this->cardPolicyService->isCardPolicyEnabled($boardId)) {
			$doneStackId = (int) ($this->cardPolicyService->getDoneStackId($boardId) ?? 0);
			if ($actorId === '' || $doneStackId <= 0) {
				throw new NoPermissionException('Operation not allowed.');
			}
			$this->cardPolicyService->assertUserAllowedForAction($boardId, $id, CardPolicyService::ACTION_VERIFY, $actorId);
			if ((int) $card->getStackId() === $doneStackId) {
				throw new StatusException('Operation not allowed. Move the card out of Done to revoke approval.');
			}
		}
		$card->setDone(null);
		$newCard = $this->cardMapper->update($card);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $newCard, ActivityManager::SUBJECT_CARD_UPDATE_UNDONE);
		$this->changeHelper->cardChanged($id, false);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));

		return $newCard;
	}

	public function isCombiProjectBoardByStackId(int $stackId): bool
	{
		if ($stackId <= 0) {
			return false;
		}

		$boardId = (int) ($this->stackMapper->findBoardId($stackId) ?? 0);
		return $this->isCombiProjectBoard($boardId);
	}

	public function isCombiProjectBoardByCardId(int $cardId): bool
	{
		if ($cardId <= 0) {
			return false;
		}

		$boardId = (int) ($this->cardMapper->findBoardId($cardId) ?? 0);
		return $this->isCombiProjectBoard($boardId);
	}

	private function isProjectLinkedBoard(int $boardId): bool
	{
		if ($boardId <= 0) {
			return false;
		}

		try {
			return $this->projectMapper->findByBoardId($boardId) instanceof Project;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function isCombiProjectBoard(int $boardId): bool
	{
		if ($boardId <= 0) {
			return false;
		}

		try {
			$project = $this->projectMapper->findByBoardId($boardId);
			return $project instanceof Project && (int) $project->getType() === 0;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function isExplicitDoneChange(Card $card, OptionalNullableValue $done): bool
	{
		$currentDone = $this->normalizeDoneTimestamp($card->getDone());
		$requestedDoneRaw = $done->getValue();
		if ($requestedDoneRaw === null) {
			return $currentDone !== null;
		}

		$requestedDone = $this->normalizeDoneTimestamp($requestedDoneRaw);
		if ($requestedDone === null) {
			return true;
		}

		return $requestedDone !== $currentDone;
	}

	private function normalizeDoneTimestamp(mixed $value): ?int
	{
		if ($value instanceof \DateTimeInterface) {
			return $value->getTimestamp();
		}

		if (is_string($value) && trim($value) !== '') {
			try {
				return (new \DateTime($value))->getTimestamp();
			} catch (\Throwable $e) {
				return null;
			}
		}

		return null;
	}

	/**
	 * @param $cardId
	 * @param $labelId
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function assignLabel($cardId, $labelId)
	{
		$this->cardServiceValidator->check(compact('cardId', 'labelId'));

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->labelMapper, $labelId, Acl::PERMISSION_READ);

		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$label = $this->labelMapper->find($labelId);
		if ($label->getBoardId() !== $this->cardMapper->findBoardId($card->getId())) {
			throw new StatusException('Operation not allowed. Label does not exist.');
		}
		$this->cardMapper->assignLabel($cardId, $labelId);
		$this->changeHelper->cardChanged($cardId);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_LABEL_ASSIGN, ['label' => $label]);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));
	}

	/**
	 * @param $cardId
	 * @param $labelId
	 * @throws StatusException
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws BadRequestException
	 */
	public function removeLabel($cardId, $labelId)
	{
		$this->cardServiceValidator->check(compact('cardId', 'labelId'));


		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);
		$this->permissionService->checkPermission($this->labelMapper, $labelId, Acl::PERMISSION_READ);

		if ($this->boardService->isArchived($this->cardMapper, $cardId)) {
			throw new StatusException('Operation not allowed. This board is archived.');
		}
		$card = $this->cardMapper->find($cardId);
		if ($card->getArchived()) {
			throw new StatusException('Operation not allowed. This card is archived.');
		}
		$label = $this->labelMapper->find($labelId);
		if ($label->getBoardId() !== $this->cardMapper->findBoardId($card->getId())) {
			throw new StatusException('Operation not allowed. Label does not exist.');
		}
		$this->cardMapper->removeLabel($cardId, $labelId);
		$this->changeHelper->cardChanged($cardId);
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $card, ActivityManager::SUBJECT_LABEL_UNASSING, ['label' => $label]);

		$this->eventDispatcher->dispatchTyped(new CardUpdatedEvent($card));
	}

	public function getCardUrl(int $cardId): string
	{
		$boardId = $this->cardMapper->findBoardId($cardId);

		return $this->urlGenerator->linkToRouteAbsolute('deck.page.indexCard', ['boardId' => $boardId, 'cardId' => $cardId]);
	}

	public function getRedirectUrlForCard(int $cardId): string
	{
		return $this->urlGenerator->linkToRouteAbsolute('deck.page.redirectToCard', ['cardId' => $cardId]);
	}
}
