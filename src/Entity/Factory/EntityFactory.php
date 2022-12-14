<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Factory;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\NotifyPropertyChanged;
use LTS\DsmRuntime\Helper\NamespaceHelper;
use LTS\DsmRuntime\DoctrineStaticMeta;
use LTS\DsmRuntime\Entity\DataTransferObjects\DtoFactory;
use LTS\DsmRuntime\Entity\Fields\Interfaces\PrimaryKey\IdFieldInterface;
use LTS\DsmRuntime\Entity\Interfaces\AlwaysValidInterface;
use LTS\DsmRuntime\Entity\Interfaces\DataTransferObjectInterface;
use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use LTS\DsmRuntime\Entity\Interfaces\UsesPHPMetaDataInterface;
use LTS\DsmRuntime\Exception\MultipleValidationException;
use LTS\DsmRuntime\Exception\ValidationException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use ts\Reflection\ReflectionClass;
use TypeError;
use function get_class;
use function is_object;
use function print_r;
use function spl_object_hash;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class EntityFactory implements EntityFactoryInterface
{
    /**
     * This array is used to track Entities that in the process of being created as part of a transaction
     *
     * @var EntityInterface[][]
     */
    private static $created = [];
    /**
     * @var NamespaceHelper
     */
    protected $namespaceHelper;
    /**
     * @var EntityDependencyInjector
     */
    protected $entityDependencyInjector;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var DtoFactory
     */
    private $dtoFactory;
    /**
     * @var array|bool[]
     */
    private $dtosProcessed;

    public function __construct(
        NamespaceHelper $namespaceHelper,
        EntityDependencyInjector $entityDependencyInjector,
        DtoFactory $dtoFactory
    ) {
        $this->namespaceHelper          = $namespaceHelper;
        $this->entityDependencyInjector = $entityDependencyInjector;
        $this->dtoFactory               = $dtoFactory;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): EntityFactoryInterface
    {
        $this->entityManager = $entityManager;

        return $this;
    }

    /**
     * Get an instance of the specific Entity Factory for a specified Entity
     *
     * Not type hinting the return because the whole point of this is to have an entity specific method, which we
     * can't hint for
     *
     * @param string $entityFqn
     *
     * @return mixed
     */
    public function createFactoryForEntity(string $entityFqn)
    {
        $this->assertEntityManagerSet();
        $factoryFqn = $this->namespaceHelper->getFactoryFqnFromEntityFqn($entityFqn);

        return new $factoryFqn($this, $this->entityManager);
    }

    private function assertEntityManagerSet(): void
    {
        if (!$this->entityManager instanceof EntityManagerInterface) {
            throw new RuntimeException(
                'No EntityManager set, this must be set first using setEntityManager()'
            );
        }
    }

    public function getEntity(string $className)
    {
        return $this->create($className);
    }

    /**
     * Build a new entity, optionally pass in a DTO to provide the data that should be used
     *
     * Optionally pass in an array of property=>value
     *
     * @param string                           $entityFqn
     *
     * @param DataTransferObjectInterface|null $dto
     *
     * @return mixed
     * @throws MultipleValidationException
     * @throws ValidationException
     */
    public function create(string $entityFqn, DataTransferObjectInterface $dto = null)
    {
        $this->assertEntityManagerSet();

        return $this->createEntity($entityFqn, $dto);
    }

    /**
     * Create the Entity
     *
     * If the update step throw an exception, then we detach the entity to prevent us having an empty entity in the
     * unit of work which would otherwise be saved to the DB
     *
     * @param string                           $entityFqn
     *
     * @param DataTransferObjectInterface|null $dto
     *
     * @param bool                             $isRootEntity
     *
     * @return EntityInterface
     * @throws MultipleValidationException
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    private function createEntity(
        string $entityFqn,
        DataTransferObjectInterface $dto = null,
        bool $isRootEntity = true
    ): EntityInterface {
        if ($isRootEntity) {
            $this->dtosProcessed = [];
        }
        if (null === $dto) {
            $dto = $this->dtoFactory->createEmptyDtoFromEntityFqn($entityFqn);
        }
        $idString = (string)$dto->getId();
        if (isset(self::$created[$entityFqn][$idString])) {
            return self::$created[$entityFqn][$idString];
        }
        try {
            #At this point a new entity is added to the unit of work
            $entity = $this->getNewInstance($entityFqn, $dto->getId());

            self::$created[$entityFqn][$idString] = $entity;

            #At this point, nested entities are added to the unit of work
            $this->updateDto($entity, $dto);
            #At this point, the entity values are set
            $entity->update($dto);

            if ($isRootEntity) {
                #Now we have persisted all the entities, we need to validate them all
                $this->stopTransaction();
            }
        } catch (ValidationException | MultipleValidationException | TypeError $e) {
            # Something has gone wrong, now we need to remove all created entities from the unit of work
            foreach (self::$created as $entities) {
                foreach ($entities as $createdEntity) {
                    if ($createdEntity instanceof EntityInterface) {
                        $this->entityManager->getUnitOfWork()->detach($createdEntity);
                    }
                }
            }
            # And then we need to ensure that they are cleared out from the created and processed arrays
            self::$created       = [];
            $this->dtosProcessed = [];
            throw $e;
        }

        return $entity;
    }

    /**
     * Build a new instance, bypassing PPP protections so that we can call private methods and set the private
     * transaction property
     *
     * @param string $entityFqn
     * @param mixed  $id
     *
     * @return EntityInterface
     */
    private function getNewInstance(string $entityFqn, mixed $id): EntityInterface
    {
        if (isset(self::$created[$entityFqn][(string)$id])) {
            throw new RuntimeException('Trying to get a new instance when one has already been created for this ID');
        }
        $reflection = $this->getDoctrineStaticMetaForEntityFqn($entityFqn)
                           ->getReflectionClass();
        $entity     = $reflection->newInstanceWithoutConstructor();

        $runInit = $reflection->getMethod(UsesPHPMetaDataInterface::METHOD_RUN_INIT);
        $runInit->setAccessible(true);
        $runInit->invoke($entity);

        $transactionProperty = $reflection->getProperty(AlwaysValidInterface::CREATION_TRANSACTION_RUNNING_PROPERTY);
        $transactionProperty->setAccessible(true);
        $transactionProperty->setValue($entity, true);

        $idSetter = $reflection->getMethod('set' . IdFieldInterface::PROP_ID);
        $idSetter->setAccessible(true);
        $idSetter->invoke($entity, $id);

        if ($entity instanceof EntityInterface) {
            $this->initialiseEntity($entity);

            $this->entityManager->persist($entity);

            return $entity;
        }
        throw new LogicException('Failed to create an instance of EntityInterface');
    }

    private function getDoctrineStaticMetaForEntityFqn(string $entityFqn): DoctrineStaticMeta
    {
        return $entityFqn::getDoctrineStaticMeta();
    }

    /**
     * Take an already instantiated Entity and perform the final initialisation steps
     *
     * @param EntityInterface $entity
     *
     * @throws \ReflectionException
     */
    public function initialiseEntity(EntityInterface $entity): void
    {
        $entity->ensureMetaDataIsSet($this->entityManager);
        $this->addListenerToEntityIfRequired($entity);
        $this->entityDependencyInjector->injectEntityDependencies($entity);
        $debugInitMethod = $entity::getDoctrineStaticMeta()
                                  ->getReflectionClass()
                                  ->getMethod(UsesPHPMetaDataInterface::METHOD_DEBUG_INIT);
        $debugInitMethod->setAccessible(true);
        $debugInitMethod->invoke($entity);
    }

    /**
     * Generally DSM Entities are using the Notify change tracking policy.
     * This ensures that they are fully set up for that
     *
     * @param EntityInterface $entity
     */
    private function addListenerToEntityIfRequired(EntityInterface $entity): void
    {
        if (!$entity instanceof NotifyPropertyChanged) {
            return;
        }
        $listener = $this->entityManager->getUnitOfWork();
        $entity->addPropertyChangedListener($listener);
    }

    private function updateDto(
        EntityInterface $entity,
        DataTransferObjectInterface $dto
    ): void {
        $this->replaceNestedDtoWithEntityInstanceIfIdsMatch($dto, $entity);
        $this->replaceNestedDtosWithNewEntities($dto);
        $this->dtosProcessed[spl_object_hash($dto)] = true;
    }

    /**
     * @param DataTransferObjectInterface $dto
     * @param EntityInterface             $entity
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function replaceNestedDtoWithEntityInstanceIfIdsMatch(
        DataTransferObjectInterface $dto,
        EntityInterface $entity
    ): void {
        $dtoHash = spl_object_hash($dto);
        if (isset($this->dtosProcessed[$dtoHash])) {
            return;
        }
        $this->dtosProcessed[$dtoHash] = true;
        $getters                       = $this->getGettersForDtosOrCollections($dto);
        if ([[], []] === $getters) {
            return;
        }
        [$dtoGetters, $collectionGetters] = array_values($getters);
        $entityFqn = get_class($entity);
        foreach ($dtoGetters as $getter) {
            $propertyName        = substr($getter, 3, -3);
            $issetAsEntityMethod = 'isset' . $propertyName . 'AsEntity';
            if (true === $dto->$issetAsEntityMethod()) {
                continue;
            }

            $got = $dto->$getter();
            if (null === $got) {
                continue;
            }
            $gotHash = spl_object_hash($got);
            if (isset($this->dtosProcessed[$gotHash])) {
                continue;
            }

            if ($got instanceof DataTransferObjectInterface) {
                if ($got::getEntityFqn() === $entityFqn && $got->getId() === $entity->getId()) {
                    $setter = 'set' . $propertyName;
                    $dto->$setter($entity);
                    continue;
                }
                $this->replaceNestedDtoWithEntityInstanceIfIdsMatch($got, $entity);
                continue;
            }

            throw new LogicException('Unexpected got item ' . get_class($got));
        }
        foreach ($collectionGetters as $getter) {
            $got = $dto->$getter();
            if (false === ($got instanceof Collection)) {
                continue;
            }
            foreach ($got as $key => $gotItem) {
                if (false === ($gotItem instanceof DataTransferObjectInterface)) {
                    continue;
                }
                if ($gotItem::getEntityFqn() === $entityFqn && $gotItem->getId() === $entity->getId()) {
                    $got->set($key, $entity);
                    continue;
                }
                $this->replaceNestedDtoWithEntityInstanceIfIdsMatch($gotItem, $entity);
            }
        }
    }

    private function getGettersForDtosOrCollections(DataTransferObjectInterface $dto): array
    {
        $dtoReflection     = new ReflectionClass(get_class($dto));
        $dtoGetters        = [];
        $collectionGetters = [];
        foreach ($dtoReflection->getMethods() as $method) {
            $methodName = $method->getName();
            if (0 !== strpos($methodName, 'get')) {
                continue;
            }
            $returnType = $method->getReturnType();
            if (null === $returnType) {
                continue;
            }
            if ($returnType instanceof \ReflectionUnionType) {
                continue;
            }
            $returnTypeName = $returnType->getName();
            if (false === \ts\stringContains($returnTypeName, '\\')) {
                continue;
            }
            $returnTypeReflection = new ReflectionClass($returnTypeName);

            if ($returnTypeReflection->implementsInterface(DataTransferObjectInterface::class)) {
                $dtoGetters[] = $methodName;
                continue;
            }
            if ($returnTypeReflection->implementsInterface(Collection::class)) {
                $collectionGetters[] = $methodName;
                continue;
            }
        }

        return [$dtoGetters, $collectionGetters];
    }

    /**
     * @param DataTransferObjectInterface $dto
     *
     * @throws MultipleValidationException
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function replaceNestedDtosWithNewEntities(DataTransferObjectInterface $dto): void
    {
        $getters = $this->getGettersForDtosOrCollections($dto);
        if ([[], []] === $getters) {
            return;
        }
        [$dtoGetters, $collectionGetters] = array_values($getters);
        foreach ($dtoGetters as $getter) {
            $propertyName        = substr($getter, 3, -3);
            $issetAsEntityMethod = 'isset' . $propertyName . 'AsEntity';
            if (true === $dto->$issetAsEntityMethod()) {
                continue;
            }

            $nestedDto = $dto->$getter();
            if (null === $nestedDto) {
                continue;
            }
            $setter = 'set' . substr($getter, 3, -3);
            $dto->$setter($this->createEntity($nestedDto::getEntityFqn(), $nestedDto, false));
        }
        foreach ($collectionGetters as $getter) {
            $nestedDto = $dto->$getter();
            if (false === ($nestedDto instanceof Collection)) {
                continue;
            }
            $this->convertCollectionOfDtosToEntities($nestedDto);
        }
    }

    /**
     * This will take an ArrayCollection of DTO objects and replace them with the Entities
     *
     * @param Collection $collection
     *
     * @throws MultipleValidationException
     * @throws ValidationException
     */
    private function convertCollectionOfDtosToEntities(Collection $collection): void
    {
        if (0 === $collection->count()) {
            return;
        }
        [$dtoFqn, $collectionEntityFqn] = $this->deriveDtoAndEntityFqnFromCollection($collection);

        foreach ($collection as $key => $dto) {
            if ($dto instanceof $collectionEntityFqn) {
                continue;
            }
            if (false === is_object($dto)) {
                throw new InvalidArgumentException('Unexpected DTO value ' .
                                                   print_r($dto, true) .
                                                   ', expected an instance of' .
                                                   $dtoFqn);
            }
            if (false === ($dto instanceof DataTransferObjectInterface)) {
                throw new InvalidArgumentException('Found none DTO item in collection, was instance of ' .
                                                   get_class($dto));
            }
            if (false === ($dto instanceof $dtoFqn)) {
                throw new InvalidArgumentException('Unexpected DTO ' . get_class($dto) . ', expected ' . $dtoFqn);
            }
            $collection->set($key, $this->createEntity($collectionEntityFqn, $dto, false));
        }
    }

    /**
     * Loop through a collection and determine the DTO and Entity Fqn it contains
     *
     * @param Collection $collection
     *
     * @return array
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function deriveDtoAndEntityFqnFromCollection(Collection $collection): array
    {
        if (0 === $collection->count()) {
            throw new RuntimeException('Collection is empty');
        }
        $dtoFqn              = null;
        $collectionEntityFqn = null;
        foreach ($collection as $dto) {
            if ($dto instanceof EntityInterface) {
                $collectionEntityFqn = get_class($dto);
                continue;
            }
            if (false === ($dto instanceof DataTransferObjectInterface)) {
                throw new InvalidArgumentException(
                    'Found none DTO item in collection, was instance of ' . get_class($dto)
                );
            }
            if (null === $dtoFqn) {
                $dtoFqn = get_class($dto);
                continue;
            }
            if (false === ($dto instanceof $dtoFqn)) {
                throw new InvalidArgumentException(
                    'Mismatched collection, expecting dtoFqn ' .
                    $dtoFqn .
                    ' but found ' .
                    get_class($dto)
                );
            }
        }
        if (null === $dtoFqn && null === $collectionEntityFqn) {
            throw new RuntimeException('Failed deriving either the DTO or Entity FQN from the collection');
        }
        if (null === $collectionEntityFqn) {
            $collectionEntityFqn = $this->namespaceHelper->getEntityFqnFromEntityDtoFqn($dtoFqn);
        }
        if (null === $dtoFqn) {
            $dtoFqn = $this->namespaceHelper->getEntityDtoFqnFromEntityFqn($collectionEntityFqn);
        }

        return [$dtoFqn, $collectionEntityFqn];
    }

    /**
     * Loop through all created entities and reset the transaction running property to false,
     * then remove the list of created entities
     *
     * @throws MultipleValidationException
     */
    private function stopTransaction(): void
    {
        $validationExceptions = [];
        foreach (self::$created as $entities) {
            foreach ($entities as $entity) {
                $transactionProperty =
                    $entity::getDoctrineStaticMeta()
                           ->getReflectionClass()
                           ->getProperty(AlwaysValidInterface::CREATION_TRANSACTION_RUNNING_PROPERTY);
                $transactionProperty->setAccessible(true);
                $transactionProperty->setValue($entity, false);
                try {
                    $entity->getValidator()->validate();
                } catch (ValidationException $validationException) {
                    $validationExceptions[] = $validationException;
                    continue;
                }
            }
        }
        if ([] !== $validationExceptions) {
            throw new MultipleValidationException($validationExceptions);
        }
        self::$created       = [];
        $this->dtosProcessed = [];
    }
}
