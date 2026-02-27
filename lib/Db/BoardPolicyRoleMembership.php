<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use DateTime;

/**
 * @method int getBoardId()
 * @method void setBoardId(int $boardId)
 * @method int getRoleId()
 * @method void setRoleId(int $roleId)
 * @method string getParticipant()
 * @method void setParticipant(string $participant)
 * @method int getParticipantType()
 * @method void setParticipantType(int $participantType)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 */
class BoardPolicyRoleMembership extends RelationalEntity
{
	public const PARTICIPANT_TYPE_USER = 0;
	public const PARTICIPANT_TYPE_GROUP = 1;
	public const PARTICIPANT_TYPE_CIRCLE = 7;

	protected $boardId;
	protected $roleId;
	protected $participant;
	protected $participantType;
	protected $createdAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('roleId', 'integer');
		$this->addType('participantType', 'integer');
		$this->addType('createdAt', 'datetime');
	}
}
