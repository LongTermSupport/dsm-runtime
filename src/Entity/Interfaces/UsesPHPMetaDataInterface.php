<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Interfaces;

use Doctrine\ORM\Mapping\ClassMetadata as DoctrineClassMetaData;
use LTS\DsmRuntime\DoctrineStaticMeta;

/**
 * @see \LTS\DsmRuntime\DoctrineStaticMeta::buildMetaData
 */
interface UsesPHPMetaDataInterface
{
    /**
     * Protected static methods starting with this prefix will be used to load property Doctrine meta data
     */
    public const METHOD_PREFIX_GET_PROPERTY_DOCTRINE_META = 'metaFor';

    /**
     * Protected static methods starting with this prefix will be used to load class level Doctrine meta data, this is
     * run after the property level metadata has been loaded
     */
    public const METHOD_PREFIX_GET_CLASS_DOCTRINE_META = 'classMetaFor';

    /**
     * private methods beginning with this will be run at construction time to do things like set up ArrayCollection
     * properties
     *
     * @var string
     */
    public const METHOD_PREFIX_INIT = 'init';

    /**
     * This method is called at construction time and performs the basic initialisation tasks such as setting
     * collection properties to be an instance of ArrayCollection
     */
    public const METHOD_RUN_INIT = 'runInitMethods';

    public const METHOD_DEBUG_INIT = 'initDebugIds';

    public static function loadMetadata(DoctrineClassMetaData $metadata): void;

    public static function getDoctrineStaticMeta(): DoctrineStaticMeta;
}
