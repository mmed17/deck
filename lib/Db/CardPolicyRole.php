<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

/**
 * @method int getPolicyId()
 * @method void setPolicyId(int $policyId)
 * @method string getAction()
 * @method void setAction(string $action)
 * @method int getRoleId()
 * @method void setRoleId(int $roleId)
 */
class CardPolicyRole extends RelationalEntity
{
	protected $policyId;
	protected $action;
	protected $roleId;

	public function __construct()
	{
		$this->addType('id', 'integer');
		$this->addType('policyId', 'integer');
		$this->addType('roleId', 'integer');
	}
}
