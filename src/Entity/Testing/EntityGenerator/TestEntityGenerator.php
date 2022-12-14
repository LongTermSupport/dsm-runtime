<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Testing\EntityGenerator;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Edmonds\MarketingEntities\Entity\Fields\Traits\Website\Platform\Search\WebsiteEngineHitLog\TimestampFieldTrait;
use LTS\DsmRuntime\DoctrineStaticMeta;
use LTS\DsmRuntime\Entity\DataTransferObjects\DtoFactory;
use LTS\DsmRuntime\Entity\Factory\EntityFactoryInterface;
use LTS\DsmRuntime\Entity\Fields\Traits\TimeStamp\CreationTimestampFieldTrait;
use LTS\DsmRuntime\Entity\Interfaces\DataTransferObjectInterface;
use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use LTS\DsmRuntime\RelationshipHelper;
use ErrorException;
use Generator;
use RuntimeException;
use TypeError;

use function in_array;
use function interface_exists;

/**
 * Class TestEntityGenerator
 *
 * This class handles utilising Faker to build up an Entity and then also possible build associated entities and handle
 * the association
 *
 * Unique columns are guaranteed to have a totally unique value in this particular process, but not between processes
 *
 * This Class provides you a few ways to generate test Entities, either in bulk or one at a time
 *ExcessiveClassComplexity
 *
 * @package LTS\DsmRuntime\Entity\Testing
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class TestEntityGenerator
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var DoctrineStaticMeta
     */
    protected $testedEntityDsm;

    /**
     * @var EntityFactoryInterface
     */
    protected $entityFactory;
    /**
     * @var DtoFactory
     */
    private $dtoFactory;
    /**
     * @var TestEntityGeneratorFactory
     */
    private $testEntityGeneratorFactory;
    /**
     * @var FakerDataFillerInterface
     */
    private $fakerDataFiller;
    /**
     * @var RelationshipHelper
     */
    private $relationshipHelper;


    /**
     * TestEntityGenerator constructor.
     *
     * @param DoctrineStaticMeta          $testedEntityDsm
     * @param EntityFactoryInterface|null $entityFactory
     * @param DtoFactory                  $dtoFactory
     * @param TestEntityGeneratorFactory  $testEntityGeneratorFactory
     * @param FakerDataFillerInterface    $fakerDataFiller
     * @param EntityManagerInterface      $entityManager
     * @param RelationshipHelper          $relationshipHelper
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(
        DoctrineStaticMeta $testedEntityDsm,
        EntityFactoryInterface $entityFactory,
        DtoFactory $dtoFactory,
        TestEntityGeneratorFactory $testEntityGeneratorFactory,
        FakerDataFillerInterface $fakerDataFiller,
        EntityManagerInterface $entityManager,
        RelationshipHelper $relationshipHelper
    ) {
        $this->testedEntityDsm            = $testedEntityDsm;
        $this->entityFactory              = $entityFactory;
        $this->dtoFactory                 = $dtoFactory;
        $this->testEntityGeneratorFactory = $testEntityGeneratorFactory;
        $this->fakerDataFiller            = $fakerDataFiller;
        $this->entityManager              = $entityManager;
        $this->relationshipHelper         = $relationshipHelper;
    }


    public function assertSameEntityManagerInstance(EntityManagerInterface $entityManager): void
    {
        if ($entityManager === $this->entityManager) {
            return;
        }
        throw new RuntimeException('EntityManager instance is not the same as the one loaded in this factory');
    }

    /**
     * Use the factory to generate a new Entity, possibly with values set as well
     *
     * @param array $values
     *
     * @return EntityInterface
     */
    public function create(array $values = []): EntityInterface
    {
        $dto = $this->dtoFactory->createEmptyDtoFromEntityFqn($this->testedEntityDsm->getReflectionClass()->getName());
        if ([] !== $values) {
            foreach ($values as $property => $value) {
                $setter = 'set' . $property;
                $dto->$setter($value);
            }
        }

        return $this->entityFactory->create(
            $this->testedEntityDsm->getReflectionClass()->getName(),
            $dto
        );
    }

    /**
     * Generate an Entity. Optionally provide an offset from the first entity
     *
     * @return EntityInterface
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function generateEntity(): EntityInterface
    {
        return $this->createEntityWithData();
    }

    private function createEntityWithData(): EntityInterface
    {
        $dto = $this->generateDto();

        return $this->entityFactory->create($this->testedEntityDsm->getReflectionClass()->getName(), $dto);
    }

    public function generateDto(): DataTransferObjectInterface
    {
        $dto = $this->dtoFactory->createEmptyDtoFromEntityFqn(
            $this->testedEntityDsm->getReflectionClass()->getName()
        );
        $this->fakerUpdateDto($dto);

        return $dto;
    }

    /**
     * Timestamp columns need to be forcibly updated with dates or they will always reflect the time of test run
     *
     * @param EntityInterface   $entity
     * @param DateTimeImmutable $date
     * @param string            $propertyName
     *
     * @see CreationTimestampFieldTrait
     */
    public function forceTimestamp(EntityInterface $entity, DateTimeImmutable $date, string $propertyName): void
    {
        $property = $entity::getDoctrineStaticMeta()
                           ->getReflectionClass()
                           ->getProperty($propertyName);
        $property->setValue($entity, $date);
    }

    public function fakerUpdateDto(DataTransferObjectInterface $dto): void
    {
        $this->fakerDataFiller->updateDtoWithFakeData($dto);
    }

    /**
     * @param EntityInterface $generated
     *
     * @throws ErrorException
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function addAssociationEntities(
        EntityInterface $generated
    ): void {
        $testedEntityReflection = $this->testedEntityDsm->getReflectionClass();
        $class                  = $testedEntityReflection->getName();
        $meta                   = $this->testedEntityDsm->getMetaData();
        $mappings               = $meta->getAssociationMappings();
        if (empty($mappings)) {
            return;
        }
        $methods        = array_map('strtolower', get_class_methods($generated));
        $relationHelper = $this->relationshipHelper;
        foreach ($mappings as $mapping) {
            $getter           = $relationHelper->getGetterFromDoctrineMapping($mapping);
            $isPlural         = $relationHelper->isPlural($mapping);
            $method           =
                ($isPlural) ? $relationHelper->getAdderFromDoctrineMapping($mapping) :
                    $relationHelper->getSetterFromDoctrineMapping($mapping);
            $mappingEntityFqn = $mapping['targetEntity'];
            $errorMessage     = "Error adding association entity $mappingEntityFqn to $class: %s";
            $this->assertInArray(
                strtolower($method),
                $methods,
                sprintf($errorMessage, $method . ' method is not defined')
            );
            try {
                $currentlySet = $generated->$getter();
            } catch (TypeError $e) {
                $currentlySet = null;
            }
            $this->addAssociation($generated, $method, $mappingEntityFqn, $currentlySet);
        }
    }

    /**
     * Stub of PHPUnit Assertion method
     *
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $error
     *
     * @throws ErrorException
     */
    protected function assertSame(mixed $expected, mixed $actual, string $error): void
    {
        if ($expected !== $actual) {
            throw new ErrorException($error);
        }
    }

    /**
     * Stub of PHPUnit Assertion method
     *
     * @param mixed  $needle
     * @param array  $haystack
     * @param string $error
     *
     * @throws ErrorException
     */
    protected function assertInArray(mixed $needle, array $haystack, string $error): void
    {
        if (false === in_array($needle, $haystack, true)) {
            throw new ErrorException($error);
        }
    }

    private function addAssociation(
        EntityInterface $generated,
        string $setOrAddMethod,
        string $mappingEntityFqn,
        $currentlySet
    ): void {
        $testEntityGenerator = $this->testEntityGeneratorFactory
            ->createForEntityFqn($mappingEntityFqn);
        switch (true) {
            case $currentlySet === null:
            case $currentlySet === []:
            case $currentlySet instanceof Collection:
                $mappingEntity = $testEntityGenerator->createEntityRelatedToEntity($generated);
                break;
            default:
                return;
        }
        $generated->$setOrAddMethod($mappingEntity);
        $this->entityManager->persist($mappingEntity);
    }

    /**
     * @param EntityInterface $entity
     *
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod - it is being used)
     */
    private function createEntityRelatedToEntity(EntityInterface $entity)
    {
        $dto = $this->generateDtoRelatedToEntity($entity);

        return $this->entityFactory->create(
            $this->testedEntityDsm->getReflectionClass()->getName(),
            $dto
        );
    }

    public function generateDtoRelatedToEntity(EntityInterface $entity): DataTransferObjectInterface
    {
        $dto = $this->dtoFactory->createDtoRelatedToEntityInstance(
            $entity,
            $this->testedEntityDsm->getReflectionClass()->getName()
        );
        $this->fakerDataFiller->updateDtoWithFakeData($dto);

        return $dto;
    }

    /**
     * Generate Entities.
     *
     * Optionally discard the first generated entities up to the value of offset
     *
     * @param int $num
     *
     * @return array|EntityInterface[]
     */
    public function generateEntities(
        int $num
    ): array {
        $entities  = [];
        $generator = $this->getGenerator($num);
        foreach ($generator as $entity) {
            $id = (string)$entity->getId();
            if (array_key_exists($id, $entities)) {
                throw new RuntimeException('Entity with ID ' . $id . ' is already generated');
            }
            $entities[$id] = $entity;
        }

        return $entities;
    }

    public function getGenerator(int $numToGenerate = 100): Generator
    {
        $entityFqn = $this->testedEntityDsm->getReflectionClass()->getName();
        $generated = 0;
        while ($generated < $numToGenerate) {
            $dto    = $this->generateDto();
            $entity = $this->entityFactory->setEntityManager($this->entityManager)->create($entityFqn, $dto);
            yield $entity;
            $generated++;
        }
    }

    /**
     * @return EntityFactoryInterface
     */
    public function getEntityFactory(): EntityFactoryInterface
    {
        return $this->entityFactory;
    }

    /**
     * @return DtoFactory
     */
    public function getDtoFactory(): DtoFactory
    {
        return $this->dtoFactory;
    }

    /**
     * @return FakerDataFillerInterface
     */
    public function getFakerDataFiller(): FakerDataFillerInterface
    {
        return $this->fakerDataFiller;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @return TestEntityGeneratorFactory
     */
    public function getTestEntityGeneratorFactory(): TestEntityGeneratorFactory
    {
        return $this->testEntityGeneratorFactory;
    }
}
