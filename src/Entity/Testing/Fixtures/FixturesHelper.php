<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Testing\Fixtures;

use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\EntityManagerInterface;
use LTS\DsmRuntime\Helper\NamespaceHelper;
use LTS\DsmRuntime\Entity\Savers\EntitySaverFactory;
use LTS\DsmRuntime\Entity\Testing\EntityGenerator\TestEntityGeneratorFactory;
use LTS\DsmRuntime\Exception\DoctrineStaticMetaException;
use LTS\DsmRuntime\Schema\Database;
use LTS\DsmRuntime\Schema\Schema;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * To be used in your Test classes. This provides you with the methods to use in your setup method to create the
 * fixtures are required
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class FixturesHelper
{
    /**
     * @var Database
     */
    protected $database;
    /**
     * @var Schema
     */
    protected $schema;
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;
    /**
     * @var FilesystemAdapter
     */
    protected $cache;
    /**
     * @var null|string
     */
    protected $cacheKey;
    /**
     * @var ORMExecutor
     */
    private $fixtureExecutor;

    /**
     * @var Loader
     */
    private $fixtureLoader;

    /**
     * Did we load cached Fixture SQL?
     *
     * @var bool
     */
    private $loadedFromCache = false;

    /**
     * Should we load cached Fixture SQL if available?
     *
     * @var bool
     */
    private $loadFromCache = true;
    /**
     * @var EntitySaverFactory
     */
    private $entitySaverFactory;
    /**
     * @var NamespaceHelper
     */
    private $namespaceHelper;
    /**
     * @var TestEntityGeneratorFactory
     */
    private $testEntityGeneratorFactory;
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        EntityManagerInterface $entityManager,
        Database $database,
        Schema $schema,
        FilesystemAdapter $cache,
        EntitySaverFactory $entitySaverFactory,
        NamespaceHelper $namespaceHelper,
        TestEntityGeneratorFactory $testEntityGeneratorFactory,
        ContainerInterface $container,
        ?string $cacheKey = null
    ) {
        $purger                           = null;
        $this->fixtureExecutor            = new ORMExecutor($entityManager, $purger);
        $this->fixtureLoader              = new Loader();
        $this->database                   = $database;
        $this->schema                     = $schema;
        $this->entityManager              = $entityManager;
        $this->cache                      = $cache;
        $this->entitySaverFactory         = $entitySaverFactory;
        $this->namespaceHelper            = $namespaceHelper;
        $this->testEntityGeneratorFactory = $testEntityGeneratorFactory;
        $this->cacheKey                   = $cacheKey;
        $this->container                  = $container;

        $this->fixtureLoader->setFixturesHelper($this);
    }

    /**
     * @param null|string $cacheKey
     */
    public function setCacheKey(?string $cacheKey): void
    {
        $this->cacheKey = $cacheKey;
    }

    public function createFixtureInstanceForEntityFqn(
        string $entityFqn,
        FixtureEntitiesModifierInterface $modifier = null
    ): AbstractEntityFixtureLoader {
        $fixtureFqn = $this->namespaceHelper->getFixtureFqnFromEntityFqn($entityFqn);

        return $this->createFixture($fixtureFqn, $modifier);
    }

    public function createFixture(
        string $fixtureFqn,
        FixtureEntitiesModifierInterface $modifier = null
    ): AbstractEntityFixtureLoader {
        return new $fixtureFqn(
            $this->testEntityGeneratorFactory,
            $this->entitySaverFactory,
            $this->namespaceHelper,
            $this->entityManager,
            $this->container,
            $modifier
        );
    }

    /**
     * Clean the DB and insert fixtures
     *
     * You can pass in a fixture loader directly which will be appended to the main fixtureLoader, or you can add
     * fixtures prior to calling this method
     *
     * We do not use the purge functionality of the `doctrine/data-fixtures` module, instead we fully drop and create
     * the database.
     *
     * @param FixtureInterface $fixture
     *
     * @throws DoctrineStaticMetaException
     */
    public function createDb(?FixtureInterface $fixture = null): void
    {
        if (null !== $fixture) {
            $this->addFixture($fixture);
        } elseif ([] === $this->fixtureLoader->getFixtures()) {
            throw new RuntimeException(
                'No fixtures have been set.'
                . 'You need to either pass in a Fixture to this method, or have called `addFixture` at least once '
                . 'before calling this method'
            );
        }
        $this->resetDb();
        $this->run();
    }

    public function addFixture(FixtureInterface $fixture): void
    {
        $this->fixtureLoader->addFixture($fixture);
    }

    public function resetDb(): void
    {
        $this->database->drop(true)->create(true);
        $this->schema->create();
    }

    public function run(): void
    {
//        $cacheKey   = $this->getCacheKey();
        $connection = $this->entityManager->getConnection();
//        if ($this->loadFromCache && $this->cache->contains($cacheKey)) {
//            $logger = $this->cache->fetch($cacheKey);
//            $logger->run($this->entityManager->getConnection());
//            $this->loadedFromCache = true;
//
//            return;
//        }

        $logger              = $this->getLogger();
        $connectionLogger    = $connection->getConfiguration()->getSQLLogger();
        $configurationLogger = $this->entityManager->getConfiguration()->getSQLLogger();

        $connection->getConfiguration()->setSQLLogger($logger);
        $this->entityManager->getConfiguration()->setSQLLogger($logger);

        $this->fixtureExecutor->execute($this->fixtureLoader->getFixtures(), true);

        $this->entityManager->getConfiguration()->setSQLLogger($configurationLogger);
        $connection->getConfiguration()->setSQLLogger($connectionLogger);

//        $this->cache->save($cacheKey, $logger);
    }

//    private function getCacheKey(): string
//    {
//        if (null !== $this->cacheKey) {
//            return $this->cacheKey;
//        }
//
//        $fixtureFqns = [];
//        foreach ($this->fixtureLoader->getFixtures() as $fixture) {
//            $fixtureFqns[] = get_class($fixture);
//        }
//
//        return md5(print_r($fixtureFqns, true));
//    }

    private function getLogger(): SQLLogger
    {
        return new QueryCachingLogger();
    }

    public function clearFixtures(): void
    {
        $this->fixtureLoader = new Loader();
    }

    /**
     * @return bool
     */
    public function isLoadedFromCache(): bool
    {
        return $this->loadedFromCache;
    }

    /**
     * @param bool $loadFromCache
     *
     * @return FixturesHelper
     */
    public function setLoadFromCache(bool $loadFromCache): FixturesHelper
    {
        $this->loadFromCache = $loadFromCache;

        return $this;
    }
}
