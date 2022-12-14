<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Fields\Traits\PrimaryKey;

use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use LTS\DsmRuntime\Entity\Fields\Factories\UuidFactory;
use LTS\DsmRuntime\MappingHelper;
use Ramsey\Uuid\UuidInterface;

/**
 * This trait implements a text based UUID primary key which will then be stored as a string
 */
trait NonBinaryUuidFieldTrait
{
    use AbstractUuidFieldTrait;

    /**
     * @param ClassMetadataBuilder $builder
     *
     * @see https://github.com/ramsey/uuid-doctrine#usage
     */
    protected static function metaForId(ClassMetadataBuilder $builder): void
    {
        $builder->createField('id', MappingHelper::TYPE_NON_BINARY_UUID)
                ->makePrimaryKey()
                ->nullable(false)
                ->unique()
                ->generatedValue('NONE')
                ->build();
    }

    public static function buildUuid(UuidFactory $uuidFactory): UuidInterface
    {
        return $uuidFactory->getUuid();
    }
}
