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
 * @method string getAction()
 * @method void setAction(string $action)
 * @method int getRoleId()
 * @method void setRoleId(int $roleId)
 */
class BoardPolicyDefaultRole extends RelationalEntity
{
	public const ACTION_MOVE = 'move';
	public const ACTION_APPROVE = 'approve';

	protected $boardId;
	protected $action;
	protected $roleId;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('roleId', 'integer');
	}
}
