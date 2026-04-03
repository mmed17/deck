<?php

declare(strict_types=1);

namespace OCA\Deck\Service;

use OCA\Deck\Db\Attachment;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCA\Deck\StatusException;
use OCA\ProjectCreatorAIO\Db\ProjectMapper;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;

class ProjectPrivateFileService implements IAttachmentService, ICustomAttachmentService {
	public function __construct(
		private IRequest $request,
		private IL10N $l10n,
		private IRootFolder $rootFolder,
		private IMimeTypeDetector $mimeTypeDetector,
		private IPreview $preview,
		private CardMapper $cardMapper,
		private ProjectMapper $projectMapper,
		private ?string $userId,
	) {
	}

	public function listAttachments(int $cardId): array {
		$folder = $this->getCardFolder($cardId, false);
		if (!$folder instanceof Folder) {
			return [];
		}

		$attachments = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof File) {
				continue;
			}

			$attachment = new Attachment();
			$attachment->setType('project_private_file');
			$attachment->setId((int) $node->getId());
			$attachment->setCardId($cardId);
			$attachment->setCreatedBy((string) $this->userId);
			$attachment->setData((string) $node->getName());
			$attachment->setLastModified((int) $node->getMTime());
			$attachment->setCreatedAt((int) $node->getMTime());
			$attachment->setDeletedAt(0);
			$attachments[] = $attachment;
		}

		return $attachments;
	}

	public function getAttachmentCount(int $cardId): int {
		return count($this->listAttachments($cardId));
	}

	public function extendData(Attachment $attachment) {
		try {
			$file = $this->resolveCardFile((int) $attachment->getCardId(), (int) $attachment->getId());
			$userFolder = $this->rootFolder->getUserFolder((string) $this->userId);
			$attachment->setExtendedData([
				'path' => $userFolder->getRelativePath($file->getPath()),
				'fileid' => $file->getId(),
				'data' => $file->getName(),
				'filesize' => $file->getSize(),
				'mimetype' => $file->getMimeType(),
				'info' => pathinfo($file->getName()),
				'hasPreview' => $this->preview->isAvailable($file),
				'permissions' => Constants::PERMISSION_ALL,
			]);
			$attachment->setData($file->getName());
			$attachment->setLastModified((int) $file->getMTime());
		} catch (NoPermissionException) {
		}

		return $attachment;
	}

	public function display(Attachment $attachment) {
		$file = $this->resolveCardFile((int) $attachment->getCardId(), (int) $attachment->getId());
		$response = new StreamResponse($file->fopen('rb'));
		$response->addHeader('Content-Disposition', 'attachment; filename="' . rawurldecode($file->getName()) . '"');
		$response->addHeader('Content-Type', $this->mimeTypeDetector->getSecureMimeType($file->getMimeType()));
		return $response;
	}

	public function create(Attachment $attachment, ?string $path = null) {
		$file = $this->getUploadedFile();
		$folder = $this->getCardFolder((int) $attachment->getCardId(), true);
		if (!$folder instanceof Folder) {
			throw new StatusException('Private folder is not available for this project.');
		}

		$fileName = $folder->getNonExistingName((string) $file['name']);
		$target = $folder->newFile($fileName);
		$content = fopen((string) $file['tmp_name'], 'rb');
		if ($content === false) {
			throw new StatusException('Could not read uploaded file content.');
		}
		$target->putContent($content);
		if (is_resource($content)) {
			fclose($content);
		}

		$attachment->setId((int) $target->getId());
		$attachment->setData((string) $target->getName());
		$attachment->setCreatedBy((string) $this->userId);
		$attachment->setCreatedAt(time());
		$attachment->setDeletedAt(0);
		$attachment->setLastModified((int) $target->getMTime());
	}

	public function update(Attachment $attachment) {
		$file = $this->resolveCardFile((int) $attachment->getCardId(), (int) $attachment->getId());
		$uploaded = $this->getUploadedFile();
		$content = fopen((string) $uploaded['tmp_name'], 'rb');
		if ($content === false) {
			throw new StatusException('Could not read uploaded file content.');
		}
		$file->putContent($content);
		if (is_resource($content)) {
			fclose($content);
		}
		$attachment->setLastModified(time());
		$attachment->setData($file->getName());
	}

	public function delete(Attachment $attachment) {
		$file = $this->resolveCardFile((int) $attachment->getCardId(), (int) $attachment->getId());
		$privateRoot = $this->getPrivateRootFolder((int) $attachment->getCardId(), false);
		if (!$privateRoot instanceof Folder) {
			throw new NoPermissionException('Private folder is not available for this project.');
		}

		$targetName = $privateRoot->getNonExistingName($file->getName());
		$targetPath = rtrim($privateRoot->getPath(), '/') . '/' . $targetName;
		$moved = $file->move($targetPath);
		if (!$moved instanceof File) {
			throw new NoPermissionException('Could not remove attachment from card.');
		}
	}

	public function allowUndo() {
		return false;
	}

	public function markAsDeleted(Attachment $attachment) {
		throw new \Exception('Not implemented');
	}

	private function getUploadedFile(): array {
		$file = $this->request->getUploadedFile('file');
		$error = null;
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];

		if (empty($file)) {
			$error = $this->l10n->t('No file uploaded or file size exceeds maximum of %s', [\OCP\Util::humanFileSize(\OCP\Util::uploadLimit())]);
		}
		if (!empty($file) && array_key_exists('error', $file) && $file['error'] !== UPLOAD_ERR_OK) {
			$error = $phpFileUploadErrors[$file['error']] ?? $this->l10n->t('Upload failed.');
		}
		if ($error !== null) {
			throw new StatusException($error);
		}
		return $file;
	}

	private function resolveCardFile(int $cardId, int $fileId): File {
		$folder = $this->getCardFolder($cardId, false);
		if (!$folder instanceof Folder) {
			throw new NoPermissionException('No permission to access private attachment.');
		}

		$node = $folder->getFirstNodeById($fileId);
		if (!$node instanceof File) {
			throw new NoPermissionException('No permission to access private attachment.');
		}

		return $node;
	}

	private function getCardFolder(int $cardId, bool $createIfMissing): ?Folder {
		$privateRoot = $this->getPrivateRootFolder($cardId, $createIfMissing);
		if (!$privateRoot instanceof Folder) {
			return null;
		}

		$card = $this->cardMapper->find($cardId, false);
		$cardFolderName = $this->toSafeFolderName((string) $card->getTitle());
		if ($cardFolderName === '') {
			$cardFolderName = 'Card';
		}

		return $this->ensureFolder($privateRoot, 'Scrumban/' . $cardFolderName, $createIfMissing);
	}

	private function getPrivateRootFolder(int $cardId, bool $createIfMissing): ?Folder {
		if ($this->userId === null || $this->userId === '') {
			return null;
		}

		$project = $this->projectMapper->findByCardId($cardId);
		if ($project === null || (int) ($project->getId() ?? 0) <= 0) {
			return null;
		}

		$link = $this->projectMapper->findPrivateFolderForUser((int) $project->getId(), (string) $this->userId);
		if ($link === null) {
			return null;
		}

		$userFolder = $this->rootFolder->getUserFolder((string) $this->userId);
		$folderId = (int) ($link->getFolderId() ?? 0);
		if ($folderId > 0) {
			$node = $userFolder->getFirstNodeById($folderId);
			if ($node instanceof Folder) {
				return $node;
			}
		}

		$folderPath = trim((string) ($link->getFolderPath() ?? ''), '/');
		if ($folderPath === '') {
			return null;
		}

		$folderName = basename($folderPath);
		return $this->ensureFolder($userFolder, $folderName, $createIfMissing);
	}

	private function ensureFolder(Folder $baseFolder, string $path, bool $createIfMissing): ?Folder {
		$parts = explode('/', trim($path, '/'));
		$currentFolder = $baseFolder;

		foreach ($parts as $part) {
			if ($part === '') {
				continue;
			}
			if (!$currentFolder->nodeExists($part)) {
				if (!$createIfMissing) {
					return null;
				}
				$currentFolder = $currentFolder->newFolder($part);
				continue;
			}

			$node = $currentFolder->get($part);
			if (!$node instanceof Folder) {
				throw new NoPermissionException('A file exists where a folder was expected.');
			}
			$currentFolder = $node;
		}

		return $currentFolder;
	}

	private function toSafeFolderName(string $name): string {
		$name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
		$name = str_replace(['/', '\\'], '-', $name);
		$name = str_replace(['<', '>', ':', '"', '|', '?', '*'], '-', $name);
		$name = trim($name);
		$name = trim($name, '.');
		return $name;
	}
}
