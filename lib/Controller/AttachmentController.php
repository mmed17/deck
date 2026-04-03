<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\Db\CardMapper;
use OCA\Deck\Service\AttachmentService;
use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;

class AttachmentController extends Controller {
	public function __construct(
		$appName,
		IRequest $request,
		private AttachmentService $attachmentService,
		private ProjectMapper $projectMapper,
		private CardMapper $cardMapper,
	) {
		parent::__construct($appName, $request);
	}

	private function toSafeFolderName(string $name): string {
		// Folder names must not contain path separators and should avoid control chars.
		$name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
		$name = str_replace(['/', '\\'], '-', $name);
		$name = str_replace(['<', '>', ':', '"', '|', '?', '*'], '-', $name);
		$name = trim($name);
		$name = trim($name, '.');
		return $name;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getAll($cardId) {
		return $this->attachmentService->findAll($cardId, true);
	}

	/**
	 * @param $cardId
	 * @param $attachmentId
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @return \OCP\AppFramework\Http\Response
	 * @throws \OCA\Deck\NotFoundException
	 */
	public function display($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->display($cardId, $attachmentId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function create($cardId) {
		$projectFolderPath = null;
		$type = (string) $this->request->getParam('type');
		$storageScope = strtolower(trim((string) $this->request->getParam('storage_scope', 'shared')));
		if (!in_array($storageScope, ['shared', 'private'], true)) {
			$storageScope = 'shared';
		}
		$project = $this->projectMapper->findByCardId($cardId);
		
		if ($project !== null && $project->getFolderPath() !== null && $type === 'file' && $storageScope === 'shared') {
			$folderName = basename($project->getFolderPath());
			$card = $this->cardMapper->find((int)$cardId, false);
			$cardFolderName = $this->toSafeFolderName((string)$card->getTitle());
			if ($cardFolderName === '') {
				$cardFolderName = 'Card';
			}
			$projectFolderPath = $folderName . '/Scrumban/' . $cardFolderName;
		}

		if ($project !== null && $type === 'file' && $storageScope === 'private') {
			$type = 'project_private_file';
		}

		return $this->attachmentService->create(
			$cardId,
			$type,
			$this->request->getParam('data'),
			$projectFolderPath
		);
	}

	/**
	 * @NoAdminRequired
	 */
	public function update($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->update($cardId, $attachmentId, $this->request->getParam('data'), $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function delete($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->delete($cardId, $attachmentId, $type);
	}

	/**
	 * @NoAdminRequired
	 */
	public function restore($cardId, $attachmentId) {
		if (!str_contains($attachmentId, ':')) {
			$type = 'deck_file';
		} else {
			[$type, $attachmentId] = explode(':', $attachmentId);
		}
		return $this->attachmentService->restore($cardId, $attachmentId, $type);
	}
}
