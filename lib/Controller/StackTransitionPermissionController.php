<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\StackTransitionPermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class StackTransitionPermissionController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private StackTransitionPermissionService $transitionPermissionService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get all transition permissions for a board
     *
     * @NoAdminRequired
     * @param int $boardId
     * @return DataResponse
     */
    public function index(int $boardId): DataResponse
    {
        try {
            $permissions = $this->transitionPermissionService->getTransitionPermissions($boardId);
            return new DataResponse($permissions);
        } catch (NoPermissionException $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching transition permissions: ' . $e->getMessage());
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add a new transition permission
     *
     * @NoAdminRequired
     * @param int $boardId
     * @param int|null $fromStackId
     * @param int $toStackId
     * @param string $requiredRole
     * @param string $participant
     * @param int $participantType
     * @return DataResponse
     */
    public function create(
        int $boardId,
        ?int $fromStackId,
        int $toStackId,
        string $requiredRole,
        string $participant,
        int $participantType
    ): DataResponse {
        try {
            $permission = $this->transitionPermissionService->addTransitionPermission(
                $boardId,
                $fromStackId,
                $toStackId,
                $requiredRole,
                $participant,
                $participantType
            );
            return new DataResponse($permission, Http::STATUS_CREATED);
        } catch (NoPermissionException $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Error creating transition permission: ' . $e->getMessage());
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a transition permission
     *
     * @NoAdminRequired
     * @param int $id
     * @return DataResponse
     */
    public function destroy(int $id): DataResponse
    {
        try {
            $this->transitionPermissionService->deleteTransitionPermission($id);
            return new DataResponse([], Http::STATUS_NO_CONTENT);
        } catch (NoPermissionException $e) {
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (\Exception $e) {
            $this->logger->error('Error deleting transition permission: ' . $e->getMessage());
            return new DataResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}
