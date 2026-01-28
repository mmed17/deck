<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getBoardId()
 * @method void setBoardId(int $boardId)
 * @method int|null getFromStackId()
 * @method void setFromStackId(?int $fromStackId)
 * @method int getToStackId()
 * @method void setToStackId(int $toStackId)
 * @method string getRequiredRole()
 * @method void setRequiredRole(string $requiredRole)
 * @method string getParticipant()
 * @method void setParticipant(string $participant)
 * @method int getParticipantType()
 * @method void setParticipantType(int $participantType)
 */
class StackTransitionPermission extends RelationalEntity
{
    // D-RASCI-VF Roles
    public const ROLE_DRIVER = 'D';
    public const ROLE_RESPONSIBLE = 'R';
    public const ROLE_ACCOUNTABLE = 'A';
    public const ROLE_SUPPORT = 'S';
    public const ROLE_CONSULTED = 'C';
    public const ROLE_INFORMED = 'I';
    public const ROLE_VERIFY = 'V';
    public const ROLE_FINAL_APPROVER = 'F';

    // Participant types (matching Acl constants)
    public const PARTICIPANT_TYPE_USER = 0;
    public const PARTICIPANT_TYPE_GROUP = 1;
    public const PARTICIPANT_TYPE_CIRCLE = 7;

    protected $boardId;
    protected $fromStackId;
    protected $toStackId;
    protected $requiredRole;
    protected $participant;
    protected $participantType;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('boardId', 'integer');
        $this->addType('fromStackId', 'integer');
        $this->addType('toStackId', 'integer');
        $this->addType('participantType', 'integer');
    }

    public static function getValidRoles(): array
    {
        return [
            self::ROLE_DRIVER,
            self::ROLE_RESPONSIBLE,
            self::ROLE_ACCOUNTABLE,
            self::ROLE_SUPPORT,
            self::ROLE_CONSULTED,
            self::ROLE_INFORMED,
            self::ROLE_VERIFY,
            self::ROLE_FINAL_APPROVER,
        ];
    }

    public static function getRoleLabel(string $role): string
    {
        $labels = [
            self::ROLE_DRIVER => 'Driver',
            self::ROLE_RESPONSIBLE => 'Responsible',
            self::ROLE_ACCOUNTABLE => 'Accountable',
            self::ROLE_SUPPORT => 'Support',
            self::ROLE_CONSULTED => 'Consulted',
            self::ROLE_INFORMED => 'Informed',
            self::ROLE_VERIFY => 'Verify',
            self::ROLE_FINAL_APPROVER => 'Final Approver',
        ];
        return $labels[$role] ?? $role;
    }
}