<?php

declare(strict_types=1);

namespace OCA\Deck\Controller;

use OCA\Deck\Db\NoteMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\AppFramework\Db\DoesNotExistException;

class NoteApiController extends Controller {

    private ?string $userId;

    public function __construct(
        string $appName, 
        IRequest $request, 
        protected NoteMapper $mapper,
        protected IUserSession $userSession
    ) {
        parent::__construct($appName, $request);
        $this->userId = $this->userSession->getUser()->getUID();
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     * Retrieves all notes for the current user on a specific card.
     */
    public function index(int $cardId, int $limit = 10, int $offset = 0): DataResponse {
        $notes = $this->mapper->findByCard(
            $this->userId, 
            $cardId, 
            $limit, 
            $offset
        );
        return new DataResponse($notes);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     * Creates a new note.
     */
    public function create(int $cardId, string $content): DataResponse {
        if (trim($content) === '') {
            return new DataResponse(['error' => 'Content cannot be empty'], Http::STATUS_BAD_REQUEST);
        }

        $note = $this->mapper->create($this->userId, $cardId, $content);
        return new DataResponse($note, Http::STATUS_CREATED);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     * Updates an existing note.
     */
    public function update(int $noteId, string $content): DataResponse {
        try {
            $note = $this->mapper->find($noteId);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['error' => 'Note not found'], Http::STATUS_NOT_FOUND);
        }

        if ($note->getUserId() !== $this->userId) {
            return new DataResponse(['error' => 'Permission denied'], Http::STATUS_FORBIDDEN);
        }

        if (trim($content) === '') {
            return new DataResponse(['error' => 'Content cannot be empty'], Http::STATUS_BAD_REQUEST);
        }

        $note->setContent($content);

        $updatedNote = $this->mapper->update($note);
        return new DataResponse($updatedNote);
    }

    /**
     * @NoCSRFRequired
     * @NoAdminRequired
     *
     * Deletes a note.
     */
    public function destroy(int $noteId): DataResponse {
        try {
            $note = $this->mapper->find($noteId);
        } catch (DoesNotExistException $e) {
            return new DataResponse(['status' => 'success'], Http::STATUS_OK);
        }

        if ($note->getUserId() !== $this->userId) {
            return new DataResponse(['error' => 'Permission denied'], Http::STATUS_FORBIDDEN);
        }

        $this->mapper->delete($note);
        return new DataResponse(['status' => 'success'], Http::STATUS_OK);
    }
}