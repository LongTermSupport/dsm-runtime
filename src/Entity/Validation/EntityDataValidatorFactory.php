<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Validation;

use LTS\DsmRuntime\Entity\Interfaces\Validation\EntityDataValidatorInterface;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\Cache\DoctrineCache;
use Symfony\Component\Validator\Validation;

class EntityDataValidatorFactory
{
    /**
     * The public static method that is called to load the validator meta data
     *
     * @see https://symfony.com/doc/current/validation.html
     *
     * @see https://symfony.com/doc/current/components/validator/resources.html#the-staticmethodloader
     *
     */
    public const METHOD_LOAD_VALIDATOR_META_DATA = 'loadValidatorMetadata';

//    protected DoctrineCache $doctrineCache;
    private ContainerConstraintValidatorFactory $factory;
    private Initialiser $initialiser;

    /**
     * ValidatorFactory constructor.
     *
     * You need to specify the cache driver implementation at the DI level
     *
     * The container constraint validator factory allows us to have constraints with dependencies that are
     * automatically using standard DI injected
     */
    public function __construct(
       // DoctrineCache $doctrineCache,
        ContainerConstraintValidatorFactory $factory,
        Initialiser $initialiser
    ) {
//        $this->doctrineCache = $doctrineCache;
        $this->factory       = $factory;
        $this->initialiser   = $initialiser;
    }

    /**
     * Build an EntityDataValidatorInterface
     *
     * @return EntityDataValidatorInterface
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function buildEntityDataValidator(): EntityDataValidatorInterface
    {
        $builder = Validation::createValidatorBuilder();
        $builder->addMethodMapping(self::METHOD_LOAD_VALIDATOR_META_DATA);
//        $builder->setMetadataCache($this->doctrineCache);
        $builder->setConstraintValidatorFactory($this->factory);
        $builder->addObjectInitializer($this->initialiser);
        $validator = $builder->getValidator();

        return new EntityDataValidator($validator);
    }
}
