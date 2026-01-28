<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Service;

use OCA\Deck\Db\StackTransitionPermission;
use OCA\Deck\Db\StackTransitionPermissionMapper;
use OCA\Deck\Db\CardMapper;
use OCA\Deck\NoPermissionException;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class StackTransitionPermissionService
{
    public function __construct(
        private StackTransitionPermissionMapper $mapper,
        private CardMapper $cardMapper,
        private PermissionService $permissionService,
        private CirclesService $circlesService,
        private IGroupManager $groupManager,
        private LoggerInterface $logger,
        private ?string $userId,
    ) {
    }

    /**
     * Check if a user has permission to move a card between stacks
     *
     * @param int $cardId
     * @param int $oldStackId
     * @param int $newStackId
     * @param string|null $userId
     * @return bool
     * @throws NoPermissionException
     */
    public function checkTransitionPermission(int $cardId, int $oldStackId, int $newStackId, ?string $userId = null): bool
    {
        if ($userId === null) {
            $userId = $this->userId;
        }

        // If moving within the same stack, always allow
        if ($oldStackId === $newStackId) {
            return true;
        }

        // Get the board ID for this card
        $boardId = $this->cardMapper->findBoardId($cardId);
        if ($boardId === null) {
            throw new NoPermissionException('Card not found');
        }

        // Check if user is board owner - they have all permissions
        if ($this->permissionService->userIsBoardOwner($boardId, $userId)) {
            return true;
        }

        // Get transition permissions for this specific move
        $permissions = $this->mapper->findByTransition($boardId, $oldStackId, $newStackId);

        // Check if the board has ANY D-RASCI-VF rules defined
        $allBoardPermissions = $this->mapper->findByBoard($boardId);

        // If no D-RASCI rules exist on this board at all, don't allow the move (backward compatible)
        if (empty($allBoardPermissions)) {
            throw new NoPermissionException('No permission rules defined for this transition. Moving cards to this column requires explicit D-RASCI-VF permission.');
        }

        // Board has D-RASCI rules, so we operate in "deny by default" mode
        // If no specific permissions exist for this transition, deny the move
        if (empty($permissions)) {
            throw new NoPermissionException('No permission rules defined for this transition. Moving cards to this column requires explicit D-RASCI-VF permission.');
        }

        // Check if user has any of the required roles for this transition
        foreach ($permissions as $permission) {
            if ($this->userHasRole($permission, $userId)) {
                return true;
            }
        }

        // No matching permission found
        throw new NoPermissionException('You do not have permission to move cards to this column. Required role: ' .
            $this->getRequiredRolesLabel($permissions));
    }

    /**
     * Check if a user has a specific role permission
     *
     * @param StackTransitionPermission $permission
     * @param string $userId
     * @return bool
     */
    private function userHasRole(StackTransitionPermission $permission, string $userId): bool
    {
        switch ($permission->getParticipantType()) {
            case StackTransitionPermission::PARTICIPANT_TYPE_USER:
                return $permission->getParticipant() === $userId;

            case StackTransitionPermission::PARTICIPANT_TYPE_GROUP:
                return $this->groupManager->isInGroup($userId, $permission->getParticipant());

            case StackTransitionPermission::PARTICIPANT_TYPE_CIRCLE:
                if ($this->circlesService->isCirclesEnabled()) {
                    try {
                        return $this->circlesService->isUserInCircle($permission->getParticipant(), $userId);
                    } catch (\Exception $e) {
                        $this->logger->info('Error checking circle membership: ' . $e->getMessage());
                        return false;
                    }
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Get a human-readable label for required roles
     *
     * @param StackTransitionPermission[] $permissions
     * @return string
     */
    private function getRequiredRolesLabel(array $permissions): string
    {
        $roles = array_unique(array_map(fn($p) => $p->getRequiredRole(), $permissions));
        $labels = array_map(fn($role) => StackTransitionPermission::getRoleLabel($role), $roles);
        return implode(' or ', $labels);
    }

    /**
     * Get all transition permissions for a board
     *
     * @param int $boardId
     * @return StackTransitionPermission[]
     */
    public function getTransitionPermissions(int $boardId): array
    {
        return $this->mapper->findByBoard($boardId);
    }

    /**
     * Add a new transition permission
     *
     * @param int $boardId
     * @param int|null $fromStackId
     * @param int $toStackId
     * @param string $requiredRole
     * @param string $participant
     * @param int $participantType
     * @return StackTransitionPermission
     * @throws \Exception
     */
    public function addTransitionPermission(
        int $boardId,
        ?int $fromStackId,
        int $toStackId,
        string $requiredRole,
        string $participant,
        int $participantType
    ): StackTransitionPermission {
        // Validate role
        if (!in_array($requiredRole, StackTransitionPermission::getValidRoles())) {
            throw new \InvalidArgumentException('Invalid role: ' . $requiredRole);
        }

        $permission = new StackTransitionPermission();
        $permission->setBoardId($boardId);
        $permission->setFromStackId($fromStackId);
        $permission->setToStackId($toStackId);
        $permission->setRequiredRole($requiredRole);
        $permission->setParticipant($participant);
        $permission->setParticipantType($participantType);

        return $this->mapper->insert($permission);
    }

    /**
     * Delete a transition permission
     *
     * @param int $id
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     */
    public function deleteTransitionPermission(int $id): void
    {
        $permission = $this->mapper->find($id);
        $this->mapper->delete($permission);
    }

    /**
     * Get the highest D-RASCI-VF role a user has for a board
     * This is a simplified version - in a real implementation, you might want
     * to store user roles separately
     *
     * @param int $boardId
     * @param string $userId
     * @return string|null
     */
    public function getUserRole(int $boardId, string $userId): ?string
    {
        $permissions = $this->mapper->findByBoard($boardId);

        foreach ($permissions as $permission) {
            if ($this->userHasRole($permission, $userId)) {
                return $permission->getRequiredRole();
            }
        }

        return null;
    }
}