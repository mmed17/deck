<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Deck\Db;

use DateTime;
use OCP\AppFramework\Db\Entity;

/**
 * D-RASCI-VF Role Profile - Organization-level reusable template
 *
 * @method int getId()
 * @method string getName()
 * @method void setName(string $name)
 * @method int getOrganizationId()
 * @method void setOrganizationId(int $organizationId)
 * @method string getOwnerId()
 * @method void setOwnerId(string $ownerId)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(?DateTime $updatedAt)
 */
class RoleProfile extends Entity implements \JsonSerializable
{
    protected ?string $name = null;
    protected ?int $organizationId = null;
    protected ?string $ownerId = null;
    protected ?DateTime $createdAt = null;
    protected ?DateTime $updatedAt = null;

    public function __construct()
    {
        $this->addType('id', 'integer');
        $this->addType('organizationId', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'organizationId' => $this->organizationId,
            'ownerId' => $this->ownerId,
            'createdAt' => $this->createdAt?->format('c'),
            'updatedAt' => $this->updatedAt?->format('c'),
        ];
    }
}
