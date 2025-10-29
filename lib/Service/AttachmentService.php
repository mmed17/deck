<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Activity\ActivityManager;
use OCA\Deck\AppInfo\Application;
use OCA\Deck\BadRequestException;
use OCA\Deck\Cache\AttachmentCacheHelper;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\AttachmentMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\Db\ChangeHelper;
use OCA\Deck\InvalidAttachmentType;
use OCA\Deck\NoPermissionException;
use OCA\Deck\NotFoundException;
use OCA\Deck\StatusException;
use OCA\Deck\Validators\AttachmentServiceValidator;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\Response;
use OCP\IL10N;
use OCP\IUserManager;

class AttachmentService {
	private $attachmentMapper;
	private $cardMapper;
	private $permissionService;
	private $userId;

	/** @var IAttachmentService[] */
	private $services = [];
	/** @var Application */
	private $application;
	/** @var AttachmentCacheHelper */
	private $attachmentCacheHelper;
	/** @var IL10N */
	private $l10n;
	/** @var ActivityManager */
	private $activityManager;
	/** @var ChangeHelper */
	private $changeHelper;
	private IUserManager $userManager;
	/** @var AttachmentServiceValidator */
	private AttachmentServiceValidator $attachmentServiceValidator;

	public function __construct(
		AttachmentMapper $attachmentMapper,
		CardMapper $cardMapper,
		IUserManager $userManager,
		ChangeHelper $changeHelper,
		PermissionService $permissionService,
		Application $application,
		AttachmentCacheHelper $attachmentCacheHelper,
		$userId,
		IL10N $l10n,
		ActivityManager $activityManager,
		AttachmentServiceValidator $attachmentServiceValidator,
	) {
		$this->attachmentMapper = $attachmentMapper;
		$this->cardMapper = $cardMapper;
		$this->permissionService = $permissionService;
		$this->userId = $userId;
		$this->application = $application;
		$this->attachmentCacheHelper = $attachmentCacheHelper;
		$this->l10n = $l10n;
		$this->activityManager = $activityManager;
		$this->changeHelper = $changeHelper;
		$this->userManager = $userManager;
		$this->attachmentServiceValidator = $attachmentServiceValidator;

		// Register shipped attachment services
		// TODO: move this to a plugin based approach once we have different types of attachments
		$this->registerAttachmentService('deck_file', FileService::class);
		$this->registerAttachmentService('file', FilesAppService::class);
	}

	/**
	 * @param string $type
	 * @param string $class
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function registerAttachmentService($type, $class) {
		$this->services[$type] = $this->application->getContainer()->query($class);
	}

	/**
	 * @param string $type
	 * @return IAttachmentService
	 * @throws InvalidAttachmentType
	 */
	public function getService($type) {
		if (isset($this->services[$type])) {
			return $this->services[$type];
		}
		throw new InvalidAttachmentType($type);
	}

	/**
	 * @param $cardId
	 * @return array
	 * @throws \OCA\Deck\NoPermissionException
	 * @throws BadRequestException
	 */
	public function findAll($cardId, $withDeleted = false) {
		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_READ);

		$attachments = $this->attachmentMapper->findAll($cardId);
		if ($withDeleted) {
			$attachments = array_merge($attachments, $this->attachmentMapper->findToDelete($cardId, false));
		}

		foreach (array_keys($this->services) as $attachmentType) {
			/** @var IAttachmentService $service */
			$service = $this->getService($attachmentType);
			if ($service instanceof ICustomAttachmentService) {
				$attachments = array_merge($attachments, $service->listAttachments((int)$cardId));
			}
		}

		foreach ($attachments as &$attachment) {
			try {
				$service = $this->getService($attachment->getType());
				$service->extendData($attachment);
				$this->addCreator($attachment);
			} catch (InvalidAttachmentType $e) {
				// Ingore invalid attachment types when extending the data
			}
		}

		return $attachments;
	}

	/**
	 * @param $cardId
	 * @return int|mixed
	 * @throws BadRequestException
	 * @throws InvalidAttachmentType
	 * @throws \OCP\DB\Exception
	 */
	public function count($cardId) {
		if (is_numeric($cardId) === false) {
			throw new BadRequestException('card id must be a number');
		}

		$count = $this->attachmentCacheHelper->getAttachmentCount((int)$cardId);
		if ($count === null) {
			$count = count($this->attachmentMapper->findAll($cardId));

			foreach (array_keys($this->services) as $attachmentType) {
				$service = $this->getService($attachmentType);
				if ($service instanceof ICustomAttachmentService) {
					$count += $service->getAttachmentCount((int)$cardId);
				}
			}

			$this->attachmentCacheHelper->setAttachmentCount((int)$cardId, $count);
		}

		return $count;
	}

	public function countBatch(array $cardIds): array {
		if (empty($cardIds)) {
			return [];
		}
		
		// Validate all IDs
		foreach ($cardIds as $cardId) {
			if (is_numeric($cardId) === false) {
				throw new BadRequestException('card id must be a number');
			}
		}
		
		// Normalize to integers
		$cardIds = array_map('intval', $cardIds);
		
		// Check cache for all cards
		$counts = [];
		$uncachedCardIds = [];
		
		foreach ($cardIds as $cardId) {
			$cachedCount = $this->attachmentCacheHelper->getAttachmentCount($cardId);
			if ($cachedCount !== null) {
				$counts[$cardId] = $cachedCount;
			} else {
				$uncachedCardIds[] = $cardId;
			}
		}
		
		// If all cached, return early
		if (empty($uncachedCardIds)) {
			return $counts;
		}
		
		// Batch fetch regular attachments
		$regularCounts = $this->attachmentMapper->countByCardIdsBatch($uncachedCardIds);
		
		// Batch fetch custom attachments
		foreach (array_keys($this->services) as $attachmentType) {
			$service = $this->getService($attachmentType);
			if ($service instanceof ICustomAttachmentService) {
				// Check if service supports batch counting
				if (method_exists($service, 'getAttachmentCountsBatch')) {
					$customCounts = $service->getAttachmentCountsBatch($uncachedCardIds);
					foreach ($customCounts as $cardId => $count) {
						$regularCounts[$cardId] = ($regularCounts[$cardId] ?? 0) + $count;
					}
				} else {
					// Fallback to single calls if batch not supported
					foreach ($uncachedCardIds as $cardId) {
						$customCount = $service->getAttachmentCount($cardId);
						$regularCounts[$cardId] = ($regularCounts[$cardId] ?? 0) + $customCount;
					}
				}
			}
		}
		
		// Cache and merge results
		foreach ($uncachedCardIds as $cardId) {
			$count = $regularCounts[$cardId] ?? 0;
			$this->attachmentCacheHelper->setAttachmentCount($cardId, $count);
			$counts[$cardId] = $count;
		}
		
		return $counts;
}


	/**
 * Gets the total attachment count for multiple cards in a batch operation.
 * It checks the cache first, then fetches any missing counts from all sources.
 *
 * @param int[] $cardIds
 * @return array An associative array mapping cardId to its total attachment count.
 */
	public function getCounts(array $cardIds): array {
		if (empty($cardIds)) {
			return [];
		}
		$cardIds = array_map('int', array_unique($cardIds));

		// 1. Bulk-check the cache for already computed counts
		$cachedCounts = $this->attachmentCacheHelper->getAttachmentCounts($cardIds);
		$cardIdsToFetch = array_diff($cardIds, array_keys($cachedCounts));

		// If all counts were found in the cache, return them immediately
		if (empty($cardIdsToFetch)) {
			return $cachedCounts;
		}

		// 2. Fetch missing counts from the database
		$fetchedCounts = array_fill_keys($cardIdsToFetch, 0);

		// a. Get counts from the primary attachment mapper
		// This assumes your mapper has a batch method.
		$mapperCounts = $this->attachmentMapper->findAllCounts($cardIdsToFetch);
		foreach ($mapperCounts as $cardId => $count) {
			$fetchedCounts[$cardId] += $count;
		}

		// b. Get counts from all custom attachment services
		foreach (array_keys($this->services) as $attachmentType) {
			$service = $this->getService($attachmentType);
			if ($service instanceof ICustomAttachmentService) {
				// Use the new batch method if it exists
				if (method_exists($service, 'getAttachmentCounts')) {
					$serviceCounts = $service->getAttachmentCounts($cardIdsToFetch);
					foreach ($serviceCounts as $cardId => $count) {
						$fetchedCounts[$cardId] += $count;
					}
				} else {
					// Fallback for services that haven't been updated (less efficient)
					foreach ($cardIdsToFetch as $cardId) {
						$fetchedCounts[$cardId] += $service->getAttachmentCount($cardId);
					}
				}
			}
		}

		// 3. Cache the newly fetched results for future requests
		if (!empty($fetchedCounts)) {
			$this->attachmentCacheHelper->setAttachmentCounts($fetchedCounts);
		}

		// 4. Merge the cached results with the newly fetched results and return
		return $cachedCounts + $fetchedCounts;
	}

	/**
	 * @param $cardId
	 * @param $type
	 * @param $data
	 * @return Attachment|\OCP\AppFramework\Db\Entity
	 * @throws NoPermissionException
	 * @throws StatusException
	 * @throws BadRequestException
	 */
	public function create($cardId, $type, $data, string $path = null) {
		$this->attachmentServiceValidator->check(compact('cardId', 'type'));

		$this->permissionService->checkPermission($this->cardMapper, $cardId, Acl::PERMISSION_EDIT);

		$this->attachmentCacheHelper->clearAttachmentCount((int)$cardId);
		$attachment = new Attachment();
		$attachment->setCardId($cardId);
		$attachment->setType($type);
		$attachment->setData($data);
		$attachment->setCreatedBy($this->userId);
		$attachment->setLastModified(time());
		$attachment->setCreatedAt(time());
		
		try {
			$service = $this->getService($attachment->getType());
			$service->create($attachment, $path);

			if (!$service instanceof ICustomAttachmentService) {
				if ($attachment->getData() === null) {
					throw new StatusException($this->l10n->t('No data was provided to create an attachment.'));
				}

				$attachment = $this->attachmentMapper->insert($attachment);
			}

			$service->extendData($attachment);
			$this->addCreator($attachment);
		} catch (InvalidAttachmentType $e) {
			// just store the data
		}

		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_CREATE);
		return $attachment;
	}


	/**
	 * Display the attachment
	 *
	 * @param $attachmentId
	 * @return Response
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function display($cardId, $attachmentId, $type = 'deck_file') {
		try {
			$service = $this->getService($type);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}

		if (!$service instanceof ICustomAttachmentService) {
			try {
				$attachment = $this->attachmentMapper->find($attachmentId);
			} catch (\Exception $e) {
				throw new NoPermissionException('Permission denied');
			}
			$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_READ);

			try {
				$service = $this->getService($attachment->getType());
			} catch (InvalidAttachmentType $e) {
				throw new NotFoundException();
			}
		} else {
			$attachment = new Attachment();
			$attachment->setId($attachmentId);
			$attachment->setType($type);
			$attachment->setCardId($cardId);
			$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_READ);
		}

		return $service->display($attachment);
	}

	/**
	 * Update an attachment with custom data
	 *
	 * @param $attachmentId
	 * @param $data
	 * @return mixed
	 * @throws BadRequestException
	 * @throws NoPermissionException
	 */
	public function update($cardId, $attachmentId, $data, $type = 'deck_file') {
		$this->attachmentServiceValidator->check(compact('cardId', 'type', 'data'));

		try {
			$service = $this->getService($type);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}

		if ($service instanceof ICustomAttachmentService) {
			try {
				$attachment = new Attachment();
				$attachment->setId($attachmentId);
				$attachment->setType($type);
				$attachment->setData($data);
				$attachment->setCardId($cardId);
				$service->update($attachment);
				$this->changeHelper->cardChanged($attachment->getCardId());
				return $attachment;
			} catch (\Exception $e) {
				throw new NotFoundException();
			}
		}

		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
		} catch (\Exception $e) {
			throw new NoPermissionException('Permission denied');
		}

		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);
		$this->attachmentCacheHelper->clearAttachmentCount($cardId);

		$attachment->setData($data);
		try {
			$service = $this->getService($attachment->getType());
			$service->update($attachment);
		} catch (InvalidAttachmentType $e) {
			// just update without further action
		}
		$attachment->setLastModified(time());
		$this->attachmentMapper->update($attachment);
		// extend data so the frontend can use it properly after creating
		$service->extendData($attachment);
		$this->addCreator($attachment);

		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_UPDATE);
		return $attachment;
	}

	/**
	 * Either mark an attachment as deleted for later removal or just remove it depending
	 * on the IAttachmentService implementation
	 *
	 * @throws NoPermissionException
	 * @throws NotFoundException
	 */
	public function delete(int $cardId, int $attachmentId, string $type = 'deck_file'): Attachment {
		try {
			$service = $this->getService($type);
		} catch (InvalidAttachmentType $e) {
			throw new NotFoundException();
		}

		if ($service instanceof ICustomAttachmentService) {
			$attachment = new Attachment();
			$attachment->setId($attachmentId);
			$attachment->setType($type);
			$attachment->setCardId($cardId);
			$service->extendData($attachment);
		} else {
			try {
				$attachment = $this->attachmentMapper->find($attachmentId);
			} catch (IMapperException $e) {
				throw new NoPermissionException('Permission denied');
			}
		}
		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);

		if ($service->allowUndo()) {
			$service->markAsDeleted($attachment);
			$attachment = $this->attachmentMapper->update($attachment);
		} else {
			$service->delete($attachment);
			if (!$service instanceof ICustomAttachmentService) {
				$attachment = $this->attachmentMapper->delete($attachment);
			}
		}

		$this->attachmentCacheHelper->clearAttachmentCount($cardId);
		$this->changeHelper->cardChanged($attachment->getCardId());
		$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_DELETE);
		return $attachment;
	}

	public function restore(int $cardId, int $attachmentId, string $type = 'deck_file'): Attachment {
		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
		} catch (\Exception $e) {
			throw new NoPermissionException('Permission denied');
		}

		$this->permissionService->checkPermission($this->cardMapper, $attachment->getCardId(), Acl::PERMISSION_EDIT);
		$this->attachmentCacheHelper->clearAttachmentCount($cardId);

		try {
			$service = $this->getService($attachment->getType());
			if ($service->allowUndo()) {
				$attachment->setDeletedAt(0);
				$attachment = $this->attachmentMapper->update($attachment);
				$this->changeHelper->cardChanged($attachment->getCardId());
				$this->activityManager->triggerEvent(ActivityManager::DECK_OBJECT_CARD, $attachment, ActivityManager::SUBJECT_ATTACHMENT_RESTORE);
				return $attachment;
			}
		} catch (InvalidAttachmentType $e) {
		}
		throw new NoPermissionException('Restore is not allowed.');
	}

	/**
	 * @param Attachment $attachment
	 * @return Attachment
	 * @throws \ReflectionException
	 */
	private function addCreator(Attachment $attachment): Attachment {
		$createdBy = $attachment->jsonSerialize()['createdBy'] ?? '';
		$creator = [
			'displayName' => $createdBy,
			'id' => $createdBy,
			'email' => null,
		];
		if ($this->userManager->userExists($createdBy)) {
			$user = $this->userManager->get($createdBy);
			$creator['displayName'] = $user->getDisplayName();
			$creator['email'] = $user->getEMailAddress();
		}
		$extendedData = $attachment->jsonSerialize()['extendedData'] ?? [];
		$extendedData['attachmentCreator'] = $creator;
		$attachment->setExtendedData($extendedData);

		return $attachment;
	}
}
