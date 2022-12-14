<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Interfaces;

use LTS\DsmRuntime\Entity\Factory\EntityFactoryInterface;
use LTS\DsmRuntime\Entity\Interfaces\Validation\EntityDataValidatorInterface;
use LTS\DsmRuntime\Exception\ValidationException;
use TypeError;

interface AlwaysValidInterface
{
    /**
     * This property is set to true when creating new Entities so that validation can be postponed until they are fully
     * created. The property is only modified in the EntityFactory using Reflection
     */
    public const CREATION_TRANSACTION_RUNNING_PROPERTY = 'creationTransactionRunning';

    public static function create(
        EntityFactoryInterface $factory,
        DataTransferObjectInterface $dto = null
    );

    /**
     * @param DataTransferObjectInterface $dto
     *
     * @throws ValidationException
     * @throws TypeError
     */
    public function update(DataTransferObjectInterface $dto): void;

    public function injectEntityDataValidator(EntityDataValidatorInterface $entityDataValidator);

    public function getValidator(): EntityDataValidatorInterface;
}
