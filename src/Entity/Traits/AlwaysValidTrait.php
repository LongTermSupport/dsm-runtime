<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Traits;

use LTS\DsmRuntime\DoctrineStaticMeta;
use LTS\DsmRuntime\Entity\Factory\EntityFactoryInterface;
use LTS\DsmRuntime\Entity\Interfaces\DataTransferObjectInterface;
use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use LTS\DsmRuntime\Entity\Interfaces\Validation\EntityDataValidatorInterface;
use LTS\DsmRuntime\Exception\ValidationException;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use ts\Reflection\ReflectionClass;
use TypeError;

trait AlwaysValidTrait
{

    /**
     * @var EntityDataValidatorInterface
     */
    private $entityDataValidator;

    /**
     * This is a special property that is manipulated via Reflection in the Entity factory.
     *
     * Whilst a transaction is running, validation is suspended, and then at the end of a transaction the full
     * validation is performed
     *
     * @var bool
     */
    private $creationTransactionRunning = false;

    final public static function create(
        EntityFactoryInterface $factory,
        DataTransferObjectInterface $dto = null
    ): self {
        /** @var EntityInterface $entity */
        $entity = (new ReflectionClass(__CLASS__))->newInstanceWithoutConstructor();
        if (false === ($entity instanceof EntityInterface)) {
            throw new RuntimeException('Invalid class instance');
        }
        $factory->initialiseEntity($entity);
        if (null !== $dto) {
            $entity->update($dto);

            return $entity;
        }
        $entity->getValidator()->validate();

        return $entity;
    }

    /**
     * Update and validate the Entity.
     *
     * The DTO can
     *  - contain data not related to this Entity, it will be ignored
     *  - not have to have all the data for this Entity, it will only update where the DTO has the setter
     *
     * The entity state after update will be validated
     *
     * Will roll back all updates if validation fails
     *
     * @param DataTransferObjectInterface $dto
     *
     * @throws ValidationException
     * @throws \ReflectionException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    final public function update(DataTransferObjectInterface $dto): void
    {
        $backup  = [];
        $setters = self::getDoctrineStaticMeta()->getSetters();
        try {
            foreach ($setters as $getterName => $setterName) {
                if (false === method_exists($dto, $getterName)) {
                    continue;
                }
                $dtoValue = $dto->$getterName();
                if ($dtoValue instanceof UuidInterface && (string)$dtoValue === (string)$this->$getterName()) {
                    continue;
                }
                if (false === $this->creationTransactionRunning) {
                    $gotValue = null;
                    try {
                        $gotValue = $this->$getterName();
                    } catch (TypeError $e) {
                        //Required items will type error on the getter as they have no value
                    }
                    if ($dtoValue === $gotValue) {
                        continue;
                    }
                    $backup[$setterName] = $gotValue;
                }

                $this->$setterName($dtoValue);
            }
            if (true === $this->creationTransactionRunning) {
                return;
            }
            $this->getValidator()->validate();
        } catch (ValidationException | TypeError $e) {
            $reflectionClass = $this::getDoctrineStaticMeta()->getReflectionClass();
            foreach ($backup as $setterName => $backupValue) {
                /**
                 * We have to use reflection here because required property setter will not accept nulls
                 * which may be the backup value, especially on new object creation
                 */
                $propertyName       = $this::getDoctrineStaticMeta()->getPropertyNameFromSetterName($setterName);
                $reflectionProperty = $reflectionClass->getProperty($propertyName);
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($this, $backupValue);
            }
            throw $e;
        }
    }

    abstract public static function getDoctrineStaticMeta(): DoctrineStaticMeta;

    public function getValidator(): EntityDataValidatorInterface
    {
        if (!$this->entityDataValidator instanceof EntityDataValidatorInterface) {
            throw new RuntimeException(
                'You must call injectEntityDataValidator before being able to update an Entity'
            );
        }

        return $this->entityDataValidator;
    }

    /**
     * This method is called automatically by the EntityFactory when initialisig the Entity, by way of the
     * EntityDependencyInjector
     *
     * @param EntityDataValidatorInterface $entityDataValidator
     */
    public function injectEntityDataValidator(EntityDataValidatorInterface $entityDataValidator): void
    {
        $this->entityDataValidator = $entityDataValidator;
        $this->entityDataValidator->setEntity($this);
    }
}
