<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Traits;

use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetaData;
use LTS\DsmRuntime\DoctrineStaticMeta;
use LTS\DsmRuntime\Entity\Interfaces\UsesPHPMetaDataInterface;
use LTS\DsmRuntime\Exception\DoctrineStaticMetaException;
use Exception;
use ReflectionException;
use ts\Reflection\ReflectionMethod;

trait UsesPHPMetaDataTrait
{

    /**
     * @var DoctrineStaticMeta
     */
    private static $doctrineStaticMeta;

    /**
     * Private constructor
     *
     * @throws ReflectionException
     */
    private function __construct()
    {
        $this->runInitMethods();
    }

    /**
     * Find and run all init methods
     * - defined in relationship traits and generally to init ArrayCollection properties
     *
     * @throws ReflectionException
     */
    private function runInitMethods(): void
    {
        $reflectionClass = self::getDoctrineStaticMeta()->getReflectionClass();
        $methods         = $reflectionClass->getMethods(\ReflectionMethod::IS_PRIVATE);
        foreach ($methods as $method) {
            if ($method instanceof ReflectionMethod) {
                $method = $method->getName();
            }
            if (
                \ts\stringStartsWith($method, UsesPHPMetaDataInterface::METHOD_PREFIX_INIT)
            ) {
                $this->$method();
            }
        }
    }

    /**
     * @return DoctrineStaticMeta
     * @throws ReflectionException
     */
    public static function getDoctrineStaticMeta(): DoctrineStaticMeta
    {
        if (null === self::$doctrineStaticMeta) {
            self::$doctrineStaticMeta = new DoctrineStaticMeta(self::class);
        }

        return self::$doctrineStaticMeta;
    }

    public static function getEntityFqn(): string
    {
        return self::class;
    }

    /**
     * Loads the class and property meta data in the class
     *
     * This is the method called by Doctrine to load the meta data
     *
     * @param DoctrineClassMetaData $metaData
     *
     * @throws DoctrineStaticMetaException
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public static function loadMetadata(DoctrineClassMetaData $metaData): void
    {
        try {
            self::getDoctrineStaticMeta()->setMetaData($metaData)->buildMetaData();
        } catch (Exception $e) {
            throw new DoctrineStaticMetaException(
                'Exception in ' . __METHOD__ . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
