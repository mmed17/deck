<?php
namespace OCA\Deck\Db;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class Project extends Entity implements \JsonSerializable {
    public $id;

    // make each column property nullable with a default
    protected ?string $name        = null;
    protected ?string $label       = null;
    protected ?string $number      = null;
    protected ?int    $type        = null;
    protected ?string $address     = null;
    protected ?string $description = null;
    protected ?string $ownerId     = null;
    protected ?string $circleId    = null;
    protected ?string $boardId     = null;
    protected ?string $folderName  = null;
    protected ?DateTime $createdAt   = null;
    protected ?DateTime $updatedAt = null;

    public function __construct() {
        $this->addType('name',        Types::STRING);
        $this->addType('label',       Types::STRING);
        $this->addType('number',      Types::STRING);
        $this->addType('type',        Types::INTEGER);
        $this->addType('address',     Types::STRING);
        $this->addType('description', Types::STRING);
        $this->addType('ownerId',     Types::STRING);
        $this->addType('circleId',    Types::STRING);
        $this->addType('boardId',     Types::STRING);
        $this->addType('folderName',  Types::STRING);
        $this->addType('createdAt',   Types::DATETIME);
        $this->addType('updatedAt',   Types::DATETIME);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'label'       => $this->name,
            'number'      => $this->number,
            'type'        => $this->type,
            'address'     => $this->address,
            'description' => $this->description,
            'ownerId'     => $this->ownerId,
            'circleId'    => $this->circleId,
            'boardId'     => $this->boardId,
            'folderName'  => $this->folderName,
            'createdAt'   => $this->createdAt,
            'updatedAt'   => $this->updatedAt
        ];
    }
}
