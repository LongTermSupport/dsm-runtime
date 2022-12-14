<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Validation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Proxy\Proxy;
use LTS\DsmRuntime\Entity\Factory\EntityFactoryInterface;
use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use TypeError;

class Initialiser implements ObjectInitializerInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    private $visited = [];
    /**
     * @var EntityFactoryInterface
     */
    private $entityFactory;

    public function __construct(EntityManagerInterface $entityManager, EntityFactoryInterface $entityFactory)
    {
        $this->entityManager = $entityManager;
        $this->entityFactory = $entityFactory;
    }

    /**
     * Initializes an object just before validation.
     *
     * @param object $object The object to validate
     */
    public function initialize($object): void
    {
        $this->initialise($object);
    }

    public function initialise(object $object): void
    {
        $this->visited = [];
        $this->initialiseObject($object);
    }

    private function initialiseObject(object $object): void
    {
        if (true === $this->isVisited($object)) {
            return;
        }
        $this->setAsVisited($object);
        $this->entityManager->initializeObject($object);
        if ($object instanceof EntityInterface) {
            $this->entityFactory->initialiseEntity($object);
            $this->initialiseProperties($object);
        }
    }

    private function isVisited(object $object): bool
    {
        return isset($this->visited[spl_object_hash($object)]);
    }

    private function setAsVisited(object $object): void
    {
        $this->visited[spl_object_hash($object)] = true;
    }

    private function initialiseProperties(EntityInterface $entity): void
    {
        $getters = $entity::getDoctrineStaticMeta()->getGetters();
        foreach ($getters as $getter) {
            try {
                $got = $entity->$getter();
            } catch (TypeError $e) {
                //getters for things that have not yet been set will return null
                //but they might be required. This should be caught by the validation, not cause a type error here
                continue;
            }
            if (false === is_object($got)) {
                continue;
            }
            if ($got instanceof Proxy) {
                $this->initialiseObject($got);
                continue;
            }
            if ($got instanceof EntityInterface) {
                $this->initialiseObject($got);
                continue;
            }
        }
    }
}
