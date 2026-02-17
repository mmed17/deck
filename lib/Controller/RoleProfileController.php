<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Controller;

use OCA\Deck\NoPermissionException;
use OCA\Deck\Service\RoleProfileService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use OCP\IUserSession;

class RoleProfileController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private RoleProfileService $profileService,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get all profiles for an organization
     *
     * @param int $organizationId
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function index(int $organizationId): JSONResponse
    {
        try {
            $profiles = $this->profileService->getProfiles($organizationId);
            return new JSONResponse($profiles);
        } catch (NoPermissionException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Get a single profile
     *
     * @param int $profileId
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function show(int $profileId): JSONResponse
    {
        try {
            $profile = $this->profileService->getProfile($profileId);
            return new JSONResponse($profile);
        } catch (NoPermissionException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Profile not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Create a new profile
     *
     * @param int $organizationId
     * @param string $name
     * @param array $permissions
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function create(int $organizationId, string $name, array $permissions = []): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $profile = $this->profileService->createProfile(
                $name,
                $organizationId,
                $user->getUID(),
                $permissions
            );

            return new JSONResponse($profile->jsonSerialize(), Http::STATUS_CREATED);
        } catch (NoPermissionException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Create a profile from an existing board's permissions
     *
     * @param int $boardId
     * @param string $name
     * @param int $organizationId
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function createFromBoard(int $boardId, string $name, int $organizationId): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $profile = $this->profileService->createProfileFromBoard(
                $boardId,
                $name,
                $organizationId,
                $user->getUID()
            );

            return new JSONResponse($profile->jsonSerialize(), Http::STATUS_CREATED);
        } catch (NoPermissionException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Apply a profile to a board
     *
     * @param int $boardId
     * @param int $profileId
     * @param bool $clearExisting
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function applyToBoard(int $boardId, int $profileId, bool $clearExisting = false): JSONResponse
    {
        try {
            $result = $this->profileService->applyProfile($profileId, $boardId, $clearExisting);
            return new JSONResponse($result);
        } catch (NoPermissionException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Profile not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Update a profile
     *
     * @param int $profileId
     * @param string $name
     * @param array|null $permissions
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function update(int $profileId, string $name, ?array $permissions = null): JSONResponse
    {
        try {
            $profile = $this->profileService->updateProfile($profileId, $name, $permissions);
            return new JSONResponse($profile->jsonSerialize());
        } catch (NoPermissionException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Profile not found'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * Delete a profile
     *
     * @param int $profileId
     * @return JSONResponse
     */
    #[NoAdminRequired]
    public function destroy(int $profileId): JSONResponse
    {
        try {
            $this->profileService->deleteProfile($profileId);
            return new JSONResponse(['status' => 'deleted']);
        } catch (NoPermissionException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (DoesNotExistException $e) {
            return new JSONResponse(['error' => 'Profile not found'], Http::STATUS_NOT_FOUND);
        }
    }
}
