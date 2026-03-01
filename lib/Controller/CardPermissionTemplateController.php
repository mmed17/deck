<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Service\CardPermissionTemplateService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CardPermissionTemplateController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly CardPermissionTemplateService $templateService,
		private readonly LoggerInterface $logger,
		private readonly ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function list(int $boardId): DataResponse {
		if ($this->userId === null || $this->userId === '') {
			return new DataResponse(['message' => 'Authentication required'], Http::STATUS_FORBIDDEN);
		}
		try {
			$items = $this->templateService->listForBoard($boardId, $this->userId);
			$canApply = $this->templateService->canApplyForBoard($boardId, $this->userId);
			$out = array_map(function ($t) use ($canApply) {
				$data = $t->jsonSerialize();
				$createdBy = (string) ($t->getCreatedBy() ?? '');
				$data['canApply'] = $canApply;
				$data['canDelete'] = $canApply || ($createdBy !== '' && $createdBy === $this->userId);
				return $data;
			}, $items);
			return new DataResponse($out);
		} catch (\OCP\AppFramework\OCS\OCSException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->error('Error listing permission templates: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function createFromBoard(int $boardId, string $name): DataResponse {
		if ($this->userId === null || $this->userId === '') {
			return new DataResponse(['message' => 'Authentication required'], Http::STATUS_FORBIDDEN);
		}
		try {
			$template = $this->templateService->createFromBoard($boardId, $name, $this->userId);
			return new DataResponse($template->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\OCP\AppFramework\OCS\OCSException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->error('Error creating permission template: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function get(int $templateId, int $boardId): DataResponse {
		if ($this->userId === null || $this->userId === '') {
			return new DataResponse(['message' => 'Authentication required'], Http::STATUS_FORBIDDEN);
		}
		try {
			$data = $this->templateService->getForBoard($templateId, $boardId, $this->userId);
			return new DataResponse($data);
		} catch (\OCP\AppFramework\OCS\OCSException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->error('Error fetching permission template: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function delete(int $templateId, ?int $boardId = null): DataResponse {
		if ($this->userId === null || $this->userId === '') {
			return new DataResponse(['message' => 'Authentication required'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->templateService->delete($templateId, $this->userId, $boardId);
			return new DataResponse(['status' => 'deleted'], Http::STATUS_OK);
		} catch (\OCP\AppFramework\OCS\OCSException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->error('Error deleting permission template: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['message' => 'Internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
