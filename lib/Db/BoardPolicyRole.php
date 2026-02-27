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
 * @method string getRoleKey()
 * @method void setRoleKey(string $roleKey)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?DateTime $updatedAt)
 */
class BoardPolicyRole extends RelationalEntity
{
	protected $boardId;
	protected $roleKey;
	protected $name;
	protected $color;
	protected $createdAt;
	protected $updatedAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}
}
