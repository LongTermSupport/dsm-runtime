<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\DataTransferObjects;

use LTS\DsmRuntime\Entity\Interfaces\DataTransferObjectInterface;
use Ramsey\Uuid\UuidInterface;

class AbstractEntityUpdateDto implements DataTransferObjectInterface
{
    /**
     * @var string
     */
    private static $entityFqn;
    /**
     * @var UuidInterface
     */
    private $id;

    public function __construct(string $entityFqn, UuidInterface $id)
    {
        self::$entityFqn = $entityFqn;
        $this->id        = $id;
    }

    public static function getEntityFqn(): string
    {
        return self::$entityFqn;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function setId(UuidInterface $id)
    {
        $this->id = $id;

        return $this;
    }
}
