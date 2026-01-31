<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use OCP\AppFramework\Db\Entity;

/**
 * D-RASCI-VF Role Profile Permission - A single permission rule within a profile
 *
 * @method int getId()
 * @method int getProfileId()
 * @method void setProfileId(int $profileId)
 * @method string|null getFromStackName()
 * @method void setFromStackName(?string $fromStackName)
 * @method string getToStackName()
 * @method void setToStackName(string $toStackName)
 * @method string getRequiredRole()
 * @method void setRequiredRole(string $requiredRole)
 * @method string getParticipant()
 * @method void setParticipant(string $participant)
 * @method int getParticipantType()
 * @method void setParticipantType(int $participantType)
 */
class RoleProfilePermission extends Entity implements \JsonSerializable
{
    // Participant types (matching StackTransitionPermission constants)
    public const PARTICIPANT_TYPE_USER = 0;
    public const PARTICIPANT_TYPE_GROUP = 1;
    public const PARTICIPANT_TYPE_CIRCLE = 7;

    protected ?int $profileId = null;
    protected ?string $fromStackName = null;
    protected ?string $toStackName = null;
    protected ?string $requiredRole = null;
    protected ?string $participant = null;
    protected ?int $participantType = null;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('profileId', 'integer');
        $this->addType('participantType', 'integer');
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'profileId' => $this->profileId,
            'fromStackName' => $this->fromStackName,
            'toStackName' => $this->toStackName,
            'requiredRole' => $this->requiredRole,
            'participant' => $this->participant,
            'participantType' => $this->participantType,
        ];
    }
}
