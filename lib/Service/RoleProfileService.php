<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use DateTime;
use OCA\Deck\Db\Acl;
use OCA\Deck\Db\RoleProfile;
use OCA\Deck\Db\RoleProfileMapper;
use OCA\Deck\Db\RoleProfilePermission;
use OCA\Deck\Db\RoleProfilePermissionMapper;
use OCA\Deck\Db\StackMapper;
use OCA\Deck\Db\StackTransitionPermission;
use OCA\Deck\Db\StackTransitionPermissionMapper;
use OCA\Deck\NoPermissionException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IGroupManager;

class RoleProfileService
{
    public function __construct(
        private RoleProfileMapper $profileMapper,
        private RoleProfilePermissionMapper $permissionMapper,
        private StackMapper $stackMapper,
        private StackTransitionPermissionMapper $transitionPermissionMapper,
        private PermissionService $permissionService,
        private IGroupManager $groupManager,
        private IDBConnection $db,
        private ?string $userId,
    ) {
    }

    /**
     * Get all profiles for an organization
     *
     * @param int $organizationId
     * @return array
     */
    public function getProfiles(int $organizationId): array
    {
        $this->assertOrganizationMember($organizationId);

        $profiles = $this->profileMapper->findByOrganization($organizationId);
        $result = [];

        foreach ($profiles as $profile) {
            $profileData = $profile->jsonSerialize();
            $profileData['permissions'] = array_map(
                fn(RoleProfilePermission $p) => $p->jsonSerialize(),
                $this->permissionMapper->findByProfile($profile->getId())
            );
            $result[] = $profileData;
        }

        return $result;
    }

    /**
     * Get a single profile with its permissions
     *
     * @param int $profileId
     * @return array
     * @throws DoesNotExistException
     */
    public function getProfile(int $profileId): array
    {
        $profile = $this->profileMapper->find($profileId);
        $this->assertOrganizationMember((int) $profile->getOrganizationId());

        $profileData = $profile->jsonSerialize();
        $profileData['permissions'] = array_map(
            fn(RoleProfilePermission $p) => $p->jsonSerialize(),
            $this->permissionMapper->findByProfile($profileId)
        );
        return $profileData;
    }

    /**
     * Create a new profile
     *
     * @param string $name
     * @param int $organizationId
     * @param string $ownerId
     * @param array $permissions Array of permission data
     * @return RoleProfile
     */
    public function createProfile(
        string $name,
        int $organizationId,
        string $ownerId,
        array $permissions = []
    ): RoleProfile {
        $this->assertOrganizationAdmin($organizationId);

        $profile = new RoleProfile();
        $profile->setName($name);
        $profile->setOrganizationId($organizationId);
        $profile->setOwnerId($ownerId);
        $profile->setCreatedAt(new DateTime());

        $profile = $this->profileMapper->insert($profile);

        // Create permissions
        foreach ($permissions as $permData) {
            $perm = new RoleProfilePermission();
            $perm->setProfileId($profile->getId());
            $perm->setFromStackName($permData['fromStackName'] ?? null);
            $perm->setToStackName($permData['toStackName']);
            $perm->setRequiredRole($permData['requiredRole']);
            $perm->setParticipant($permData['participant']);
            $perm->setParticipantType($permData['participantType'] ?? RoleProfilePermission::PARTICIPANT_TYPE_USER);
            $this->permissionMapper->insert($perm);
        }

        return $profile;
    }

    /**
     * Create a profile from an existing board's permissions
     *
     * @param int $boardId
     * @param string $name
     * @param int $organizationId
     * @param string $ownerId
     * @return RoleProfile
     */
    public function createProfileFromBoard(
        int $boardId,
        string $name,
        int $organizationId,
        string $ownerId
    ): RoleProfile {
        $this->assertOrganizationAdmin($organizationId);
        $this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);

        // Get existing permissions from board
        $existingPermissions = $this->transitionPermissionMapper->findByBoard($boardId);

        // Get stacks for name resolution
        $stacks = $this->stackMapper->findAll($boardId);
        $stackIdToName = [];
        foreach ($stacks as $stack) {
            $stackIdToName[$stack->getId()] = $stack->getTitle();
        }

        // Convert to profile permissions (IDs -> Names)
        $profilePermissions = [];
        foreach ($existingPermissions as $perm) {
            $fromStackName = null;
            if ($perm->getFromStackId() !== null) {
                $fromStackName = $stackIdToName[$perm->getFromStackId()] ?? null;
            }
            $toStackName = $stackIdToName[$perm->getToStackId()] ?? null;

            if ($toStackName === null) {
                // Skip if we can't resolve the target stack
                continue;
            }

            $profilePermissions[] = [
                'fromStackName' => $fromStackName,
                'toStackName' => $toStackName,
                'requiredRole' => $perm->getRequiredRole(),
                'participant' => $perm->getParticipant(),
                'participantType' => $perm->getParticipantType(),
            ];
        }

        return $this->createProfile($name, $organizationId, $ownerId, $profilePermissions);
    }

    /**
     * Apply a profile to a board
     *
     * @param int $profileId
     * @param int $boardId
     * @param bool $clearExisting Whether to clear existing permissions first
     * @return array Result with statistics
     * @throws DoesNotExistException
     */
    public function applyProfile(int $profileId, int $boardId, bool $clearExisting = false): array
    {
        $this->permissionService->checkPermission(null, $boardId, Acl::PERMISSION_MANAGE);

        $profile = $this->profileMapper->find($profileId);
        $this->assertOrganizationMember((int) $profile->getOrganizationId());

        $profilePermissions = $this->permissionMapper->findByProfile($profileId);

        // Get stacks for name resolution
        $stacks = $this->stackMapper->findAll($boardId);
        $stackNameToId = [];
        foreach ($stacks as $stack) {
            $stackNameToId[$stack->getTitle()] = $stack->getId();
        }

        // Clear existing permissions if requested
        if ($clearExisting) {
            $this->transitionPermissionMapper->deleteByBoard($boardId);
        }

        $created = 0;
        $skipped = 0;

        foreach ($profilePermissions as $profilePerm) {
            // Resolve stack names to IDs
            $fromStackId = null;
            if ($profilePerm->getFromStackName() !== null) {
                $fromStackId = $stackNameToId[$profilePerm->getFromStackName()] ?? null;
                if ($fromStackId === null) {
                    // Source stack doesn't exist in target board
                    $skipped++;
                    continue;
                }
            }

            $toStackId = $stackNameToId[$profilePerm->getToStackName()] ?? null;
            if ($toStackId === null) {
                // Target stack doesn't exist in target board
                $skipped++;
                continue;
            }

            // Create the actual permission
            $perm = new StackTransitionPermission();
            $perm->setBoardId($boardId);
            $perm->setFromStackId($fromStackId);
            $perm->setToStackId($toStackId);
            $perm->setRequiredRole($profilePerm->getRequiredRole());
            $perm->setParticipant($profilePerm->getParticipant());
            $perm->setParticipantType($profilePerm->getParticipantType());

            $this->transitionPermissionMapper->insert($perm);
            $created++;
        }

        return [
            'profileId' => $profileId,
            'profileName' => $profile->getName(),
            'boardId' => $boardId,
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Delete a profile and all its permissions
     *
     * @param int $profileId
     * @throws DoesNotExistException
     */
    public function deleteProfile(int $profileId): void
    {
        $profile = $this->profileMapper->find($profileId);
        $this->assertOrganizationAdmin((int) $profile->getOrganizationId());

        $this->permissionMapper->deleteByProfile($profileId);
        $this->profileMapper->delete($profile);
    }

    /**
     * Update a profile
     *
     * @param int $profileId
     * @param string $name
     * @param array|null $permissions If provided, replaces all permissions
     * @return RoleProfile
     * @throws DoesNotExistException
     */
    public function updateProfile(int $profileId, string $name, ?array $permissions = null): RoleProfile
    {
        $profile = $this->profileMapper->find($profileId);
        $this->assertOrganizationAdmin((int) $profile->getOrganizationId());

        $profile->setName($name);
        $profile->setUpdatedAt(new DateTime());
        $this->profileMapper->update($profile);

        if ($permissions !== null) {
            // Replace all permissions
            $this->permissionMapper->deleteByProfile($profileId);
            foreach ($permissions as $permData) {
                $perm = new RoleProfilePermission();
                $perm->setProfileId($profileId);
                $perm->setFromStackName($permData['fromStackName'] ?? null);
                $perm->setToStackName($permData['toStackName']);
                $perm->setRequiredRole($permData['requiredRole']);
                $perm->setParticipant($permData['participant']);
                $perm->setParticipantType($permData['participantType'] ?? RoleProfilePermission::PARTICIPANT_TYPE_USER);
                $this->permissionMapper->insert($perm);
            }
        }

        return $profile;
    }

    private function assertOrganizationMember(int $organizationId): void
    {
        if ($this->isGlobalAdmin()) {
            return;
        }

        if ($this->getOrganizationMembership($organizationId) === null) {
            throw new NoPermissionException('You are not a member of this organization');
        }
    }

    private function assertOrganizationAdmin(int $organizationId): void
    {
        if ($this->isGlobalAdmin()) {
            return;
        }

        $membership = $this->getOrganizationMembership($organizationId);
        if ($membership === null || (string) ($membership['role'] ?? '') !== 'admin') {
            throw new NoPermissionException('Only organization admins can manage role profiles');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getOrganizationMembership(int $organizationId): ?array
    {
        if ($this->userId === null) {
            throw new NoPermissionException('Authentication required');
        }

        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('role')
                ->from('organization_members')
                ->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR)))
                ->setMaxResults(1);

            $result = $qb->executeQuery();
            $row = $result->fetch();
            $result->closeCursor();

            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            throw new NoPermissionException('Unable to verify organization membership');
        }
    }

    private function isGlobalAdmin(): bool
    {
        return $this->userId !== null && $this->groupManager->isAdmin($this->userId);
    }
}
