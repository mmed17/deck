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
 * @method string getPermissionMode()
 * @method void setPermissionMode(string $permissionMode)
 * @method int|null getApprovedStackId()
 * @method void setApprovedStackId(?int $approvedStackId)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?DateTime $updatedAt)
 */
class BoardPolicySetting extends RelationalEntity
{
	public const MODE_LEGACY = 'legacy';
	public const MODE_CARD_POLICY = 'card_policy';

	protected $boardId;
	protected $permissionMode;
	protected $approvedStackId;
	protected $createdAt;
	protected $updatedAt;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('boardId', 'integer');
		$this->addType('approvedStackId', 'integer');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}
}
