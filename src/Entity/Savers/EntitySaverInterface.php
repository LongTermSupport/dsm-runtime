<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Savers;

use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use LTS\DsmRuntime\Exception\DoctrineStaticMetaException;
use ReflectionException;

interface EntitySaverInterface
{
    /**
     * @param EntityInterface $entity
     *
     * @throws DoctrineStaticMetaException
     * @throws ReflectionException
     */
    public function save(EntityInterface $entity): void;

    /**
     * @param array|EntityInterface[] $entities
     *
     * @throws ReflectionException
     */
    public function saveAll(array $entities): void;

    /**
     * @param EntityInterface $entity
     *
     * @throws DoctrineStaticMetaException
     * @throws ReflectionException
     */
    public function remove(EntityInterface $entity): void;

    /**
     * @param array|EntityInterface[] $entities
     *
     * @throws DoctrineStaticMetaException
     * @throws ReflectionException
     */
    public function removeAll(array $entities): void;
}
