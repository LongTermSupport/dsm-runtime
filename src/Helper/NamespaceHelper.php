<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Helper;

use LTS\DsmRuntime\Config;
use LTS\DsmRuntime\DoctrineStaticMeta;
use LTS\DsmRuntime\Exception\DoctrineStaticMetaException;
use LTS\DsmRuntime\MappingHelper;
use LTS\DsmRuntime\RelationshipHelper;
use Exception;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use function array_merge;
use function array_slice;
use function implode;
use function in_array;
use function str_replace;
use function strlen;
use function strrpos;
use function substr;
use function ucfirst;

/**
 * Class NamespaceHelper
 *
 * Pure functions for working with namespaces and to calculate namespaces
 *
 * @package LTS\DsmRuntime\Helper
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class NamespaceHelper
{
    public function getAllArchetypeFieldFqns(): array
    {
        $archetypeFqns = [];
        $namespaceBase = 'EdmondsCommerce\\DoctrineStaticMeta\\Entity\\Fields\\Traits';
        $finder        = (new Finder())->files()
                                       ->name('*.php')
                                       ->in(__DIR__ . '/../Entity/Fields/Traits/');
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $realpath = $file->getRealPath();
            if (\ts\stringContains($realpath, '/PrimaryKey/')) {
                continue;
            }
            $subPath         = substr(
                $realpath,
                strpos($realpath, 'Entity/Fields/Traits/') + strlen('Entity/Fields/Traits/')
            );
            $subPath         = substr($subPath, 0, -4);
            $subFqn          = str_replace('/', '\\', $subPath);
            $archetypeFqns[] = $namespaceBase . '\\' . $subFqn;
        }

        return $archetypeFqns;
    }

    public function swapSuffix(string $fqn, string $currentSuffix, string $newSuffix): string
    {
        $this->assertNotCompound($fqn);

        return $this->cropSuffix($fqn, $currentSuffix) . $newSuffix;
    }

    /**
     * Crop a suffix from an FQN if it is there.
     *
     * If it is not there, do nothing and return the FQN as is
     *
     * @param string $fqn
     * @param string $suffix
     *
     * @return string
     */
    public function cropSuffix(string $fqn, string $suffix): string
    {
        $this->assertNotCompound($fqn);
        if ($suffix === substr($fqn, -strlen($suffix))) {
            return substr($fqn, 0, -strlen($suffix));
        }

        return $fqn;
    }

    public function getEmbeddableObjectFqnFromEmbeddableObjectInterfaceFqn(string $interfaceFqn): string
    {
        $this->assertNotCompound($interfaceFqn);

        return str_replace(
            ['\\Interfaces\\', 'Interface'],
            ['\\', ''],
            $interfaceFqn
        );
    }

    /**
     * @param mixed|object $object
     *
     * @return string
     */
    public function getObjectShortName(mixed $object): string
    {
        return $this->getClassShortName($this->getObjectFqn($object));
    }

    /**
     * @param string $className
     *
     * @return string
     */
    public function getClassShortName(string $className): string
    {
        $exp = explode('\\', $className);

        return end($exp);
    }

    /**
     * @param mixed|object $object
     *
     * @return string
     */
    public function getObjectFqn(mixed $object): string
    {
        return $object::class;
    }

    /**
     * Get the basename of a namespace
     *
     * @param string $namespace
     *
     * @return string
     */
    public function basename(string $namespace): string
    {
        $this->assertNotCompound($namespace);
        $strrpos = strrpos($namespace, '\\');
        if (false === $strrpos) {
            return $namespace;
        }

        return $this->tidy(substr($namespace, $strrpos + 1));
    }

    /**
     * Checks and tidies up a given namespace
     *
     * @param string $namespace
     *
     * @return string
     * @throws RuntimeException
     */
    public function tidy(string $namespace): string
    {
        $this->assertNotCompound($namespace);
        if (\ts\stringContains($namespace, '/')) {
            throw new RuntimeException('Invalid namespace ' . $namespace);
        }
        #remove repeated separators
        $namespace = preg_replace(
            '#' . '\\\\' . '+#',
            '\\',
            $namespace
        );

        return $namespace;
    }

    /**
     * Get the fully qualified name of the Fixture class for a specified Entity fully qualified name
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getFixtureFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return str_replace(
                   '\\Entities',
                   '\\Assets\\Entity\\Fixtures',
                   $entityFqn
               ) . 'Fixture';
    }

    /**
     * Get the fully qualified name of the Entity for a specified Entity fully qualified name
     *
     * @param string $fixtureFqn
     *
     * @return string
     */
    public function getEntityFqnFromFixtureFqn(string $fixtureFqn): string
    {
        $this->assertNotCompound($fixtureFqn);

        return substr(
            str_replace(
                '\\Assets\\Entity\\Fixtures',
                '\\Entities',
                $fixtureFqn
            ),
            0,
            -strlen('Fixture')
        );
    }

    /**
     * Get the namespace root up to and including a specified directory
     *
     * @param string $fqn
     * @param string $directory
     *
     * @return null|string
     */
    public function getNamespaceRootToDirectoryFromFqn(string $fqn, string $directory): ?string
    {
        $this->assertNotCompound($fqn);
        $strPos = strrpos(
            $fqn,
            $directory
        );
        if (false !== $strPos) {
            return $this->tidy(substr($fqn, 0, $strPos + strlen($directory)));
        }

        return null;
    }

    /**
     * Get the sub path for an Entity file, start from the Entities path - normally `/path/to/project/src/Entities`
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getEntityFileSubPath(
        string $entityFqn
    ): string {
        $this->assertNotCompound($entityFqn);

        return $this->getEntitySubPath($entityFqn) . '.php';
    }

    /**
     * Get the folder structure for an Entity, start from the Entities path - normally `/path/to/project/src/Entities`
     *
     * This is not the path to the file, but the sub path of directories for storing entity related items.
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getEntitySubPath(
        string $entityFqn
    ): string {
        $this->assertNotCompound($entityFqn);
        $entityPath = str_replace(
            '\\',
            '/',
            $this->getEntitySubNamespace($entityFqn)
        );

        return '/' . $entityPath;
    }

    /**
     * Get the Namespace for an Entity, start from the Entities Fully Qualified Name base - normally
     * `\My\Project\Entities\`
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getEntitySubNamespace(
        string $entityFqn
    ): string {
        $this->assertNotCompound($entityFqn);

        return $this->tidy(
            substr(
                $entityFqn,
                strrpos(
                    $entityFqn,
                    '\\' . DoctrineStaticMeta::ENTITIES_FOLDER_NAME . '\\'
                )
                + strlen('\\' . DoctrineStaticMeta::ENTITIES_FOLDER_NAME . '\\')
            )
        );
    }

    /**
     * Get the Fully Qualified Namespace root for Traits for the specified Entity
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getTraitsNamespaceForEntity(
        string $entityFqn
    ): string {
        $this->assertNotCompound($entityFqn);
        $traitsNamespace = $this->getProjectNamespaceRootFromEntityFqn($entityFqn)
                           . DoctrineStaticMeta::ENTITY_RELATIONS_NAMESPACE
                           . '\\' . $this->getEntitySubNamespace($entityFqn)
                           . '\\Traits';

        return $traitsNamespace;
    }

    /**
     * Use the fully qualified name of two Entities to calculate the Project Namespace Root
     *
     * - note: this assumes a single namespace level for entities, eg `Entities`
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getProjectNamespaceRootFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return $this->tidy(
            substr(
                $entityFqn,
                0,
                strrpos(
                    $entityFqn,
                    '\\' . DoctrineStaticMeta::ENTITIES_FOLDER_NAME . '\\'
                )
            )
        );
    }

    /**
     * Get the Fully Qualified Namespace for the "HasEntities" interface for the specified Entity
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getHasPluralInterfaceFqnForEntity(
        string $entityFqn
    ): string {
        $this->assertNotCompound($entityFqn);
        $interfaceNamespace = $this->getInterfacesNamespaceForEntity($entityFqn);

        return $interfaceNamespace . '\\Has' . ucfirst($entityFqn::getDoctrineStaticMeta()->getPlural()) . 'Interface';
    }

    /**
     * Get the Fully Qualified Namespace root for Interfaces for the specified Entity
     *
     * @param string $entityFqn
     *
     * @return string
     */
    public function getInterfacesNamespaceForEntity(
        string $entityFqn
    ): string {
        $this->assertNotCompound($entityFqn);
        $interfacesNamespace = $this->getProjectNamespaceRootFromEntityFqn($entityFqn)
                               . DoctrineStaticMeta::ENTITY_RELATIONS_NAMESPACE
                               . '\\' . $this->getEntitySubNamespace($entityFqn)
                               . '\\Interfaces';

        return $this->tidy($interfacesNamespace);
    }

    /**
     * Get the Fully Qualified Namespace for the "HasEntity" interface for the specified Entity
     *
     * @param string $entityFqn
     *
     * @return string
     * @throws DoctrineStaticMetaException
     */
    public function getHasSingularInterfaceFqnForEntity(
        string $entityFqn
    ): string {
        $this->assertNotCompound($entityFqn);
        try {
            $interfaceNamespace = $this->getInterfacesNamespaceForEntity($entityFqn);

            return $interfaceNamespace . '\\Has' . ucfirst($entityFqn::getDoctrineStaticMeta()->getSingular())
                   . 'Interface';
        } catch (Exception $e) {
            throw new DoctrineStaticMetaException(
                'Exception in ' . __METHOD__ . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get the Fully Qualified Namespace for the Relation Trait for a specific Entity and hasType
     *
     * @param string      $hasType
     * @param string      $ownedEntityFqn
     * @param string|null $projectRootNamespace
     * @param string      $srcFolder
     *
     * @return string
     * @throws DoctrineStaticMetaException
     */
    public function getOwningTraitFqn(
        string  $hasType,
        string  $ownedEntityFqn,
        ?string $projectRootNamespace = null,
        string  $srcFolder = DoctrineStaticMeta::DEFAULT_SRC_SUBFOLDER
    ): string {
        $this->assertNotCompound($ownedEntityFqn);
        try {
            $ownedHasName = $this->getOwnedHasName($hasType, $ownedEntityFqn, $srcFolder, $projectRootNamespace);
            if (null === $projectRootNamespace) {
                $projectRootNamespace = $this->getProjectRootNamespaceFromComposerJson($srcFolder);
            }
            [$ownedClassName, , $ownedSubDirectories] = $this->parseFullyQualifiedName(
                $ownedEntityFqn,
                $srcFolder,
                $projectRootNamespace
            );
            $traitSubDirectories = array_slice($ownedSubDirectories, 2);
            $owningTraitFqn      = $this->getOwningRelationsRootFqn(
                $projectRootNamespace,
                $traitSubDirectories
            );
            $required            = \ts\stringContains($hasType, RelationshipHelper::PREFIX_REQUIRED)
                ? RelationshipHelper::PREFIX_REQUIRED
                : '';
            $owningTraitFqn      .= $ownedClassName . '\\Traits\\Has' . $required . $ownedHasName
                                    . '\\' . $this->getBaseHasTypeTraitFqn($ownedHasName, $hasType);

            return $this->tidy($owningTraitFqn);
        } catch (Exception $e) {
            throw new DoctrineStaticMetaException(
                'Exception in ' . __METHOD__ . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Based on the $hasType, we calculate exactly what type of `Has` we have
     *
     * @param string $hasType
     * @param string $ownedEntityFqn
     * @param string $srcOrTestSubFolder
     *
     * @param string $projectRootNamespace
     *
     * @return string
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @throws DoctrineStaticMetaException
     */
    public function getOwnedHasName(
        string $hasType,
        string $ownedEntityFqn,
        string $srcOrTestSubFolder,
        string $projectRootNamespace
    ): string {
        $this->assertNotCompound($ownedEntityFqn);
        $parsedFqn = $this->parseFullyQualifiedName(
            $ownedEntityFqn,
            $srcOrTestSubFolder,
            $projectRootNamespace
        );

        $subDirectories = $parsedFqn[2];

        if (
            in_array(
                $hasType,
                RelationshipHelper::HAS_TYPES_PLURAL,
                true
            )
        ) {
            return $this->getPluralNamespacedName($ownedEntityFqn, $subDirectories);
        }

        return $this->getSingularNamespacedName($ownedEntityFqn, $subDirectories);
    }

    /**
     * From the fully qualified name, parse out:
     *  - class name,
     *  - namespace
     *  - the namespace parts not including the project root namespace
     *
     * @param string      $fqn
     *
     * @param string      $srcOrTestSubFolder eg 'src' or 'test'
     *
     * @param string|null $projectRootNamespace
     *
     * @return array [$className,$namespace,$subDirectories]
     * @throws DoctrineStaticMetaException
     */
    public function parseFullyQualifiedName(
        string $fqn,
        string $srcOrTestSubFolder = DoctrineStaticMeta::DEFAULT_SRC_SUBFOLDER,
        string $projectRootNamespace = null
    ): array {
        $this->assertNotCompound($fqn);
        try {
            $fqn = $this->root($fqn);
            if (null === $projectRootNamespace) {
                $projectRootNamespace = $this->getProjectRootNamespaceFromComposerJson($srcOrTestSubFolder);
            }
            $projectRootNamespace = $this->root($projectRootNamespace);
            if (false === \ts\stringContains($fqn, $projectRootNamespace)) {
                throw new DoctrineStaticMetaException(
                    'The $fqn [' . $fqn . '] does not contain the project root namespace'
                    . ' [' . $projectRootNamespace . '] - are you sure it is the correct FQN?'
                );
            }
            $fqnParts       = explode('\\', $fqn);
            $className      = array_pop($fqnParts);
            $namespace      = implode('\\', $fqnParts);
            $rootParts      = explode('\\', $projectRootNamespace);
            $subDirectories = [];
            foreach ($fqnParts as $k => $fqnPart) {
                if (isset($rootParts[$k]) && $rootParts[$k] === $fqnPart) {
                    continue;
                }
                $subDirectories[] = $fqnPart;
            }
            array_unshift($subDirectories, $srcOrTestSubFolder);

            return [
                $className,
                $this->root($namespace),
                $subDirectories,
            ];
        } catch (Exception $e) {
            throw new DoctrineStaticMetaException(
                'Exception in ' . __METHOD__ . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Generate a tidy root namespace without a leading \
     *
     * @param string $namespace
     *
     * @return string
     */
    public function root(string $namespace): string
    {
        $this->assertNotCompound($namespace);

        return $this->tidy(ltrim($namespace, '\\'));
    }

    /**
     * Read src autoloader from composer json
     *
     * @param string $dirForNamespace
     *
     * @return string
     * @throws DoctrineStaticMetaException
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getProjectRootNamespaceFromComposerJson(
        string $dirForNamespace = 'src'
    ): string {
        try {
            $dirForNamespace = trim($dirForNamespace, '/');
            $jsonPath        = Config::getProjectRootDirectory() . '/composer.json';
            $json            = json_decode(\ts\file_get_contents($jsonPath), true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new RuntimeException(
                    'Error decoding json from path ' . $jsonPath . ' , ' . json_last_error_msg()
                );
            }
            /**
             * @var string[][][][] $json
             */
            if (isset($json['autoload']['psr-4'])) {
                foreach ($json['autoload']['psr-4'] as $namespace => $dirs) {
                    if (!is_array($dirs)) {
                        $dirs = [$dirs];
                    }
                    foreach ($dirs as $dir) {
                        $dir = trim($dir, '/');
                        if ($dir === $dirForNamespace) {
                            return $this->tidy(rtrim($namespace, '\\'));
                        }
                    }
                }
            }
        } catch (Exception $e) {
            throw new DoctrineStaticMetaException(
                'Exception in ' . __METHOD__ . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        throw new DoctrineStaticMetaException('Failed to find psr-4 namespace root');
    }

    /**
     * @param string $entityFqn
     * @param array  $subDirectories
     *
     * @return string
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getPluralNamespacedName(string $entityFqn, array $subDirectories): string
    {
        $this->assertNotCompound($entityFqn);
        $plural = ucfirst(MappingHelper::getPluralForFqn($entityFqn));

        return $this->getNamespacedName($plural, $subDirectories);
    }

    /**
     * @param string $entityName
     * @param array  $subDirectories
     *
     * @return string
     */
    public function getNamespacedName(string $entityName, array $subDirectories): string
    {
        $noEntitiesDirectory = array_slice($subDirectories, 2);
        $namespacedName      = array_merge($noEntitiesDirectory, [$entityName]);

        return ucfirst(implode('', $namespacedName));
    }

    /**
     * @param string $entityFqn
     * @param array  $subDirectories
     *
     * @return string
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function getSingularNamespacedName(string $entityFqn, array $subDirectories): string
    {
        $this->assertNotCompound($entityFqn);
        $singular = ucfirst(MappingHelper::getSingularForFqn($entityFqn));

        return $this->getNamespacedName($singular, $subDirectories);
    }

    /**
     * Get the Namespace root for Entity Relations
     *
     * @param string $projectRootNamespace
     * @param array  $subDirectories
     *
     * @return string
     */
    public function getOwningRelationsRootFqn(
        string $projectRootNamespace,
        array  $subDirectories
    ): string {
        $this->assertNotCompound($projectRootNamespace);
        $relationsRootFqn = $projectRootNamespace
                            . DoctrineStaticMeta::ENTITY_RELATIONS_NAMESPACE . '\\';
        if (count($subDirectories) > 0) {
            $relationsRootFqn .= implode('\\', $subDirectories) . '\\';
        }

        return $this->tidy($relationsRootFqn);
    }

    /**
     * Normalise a has type, removing prefixes that are not required
     *
     * Inverse hasTypes use the standard template without the prefix
     * The exclusion ot this are the ManyToMany and OneToOne relations
     *
     * @param string $ownedHasName
     * @param string $hasType
     *
     * @return string
     */
    public function getBaseHasTypeTraitFqn(
        string $ownedHasName,
        string $hasType
    ): string {
        $required = \ts\stringContains($hasType, RelationshipHelper::PREFIX_REQUIRED)
            ? RelationshipHelper::PREFIX_REQUIRED
            : '';

        $hasType = str_replace(RelationshipHelper::PREFIX_REQUIRED, '', $hasType);
        foreach (
            [
                RelationshipHelper::INTERNAL_TYPE_MANY_TO_MANY,
                RelationshipHelper::INTERNAL_TYPE_ONE_TO_ONE,
            ] as $noStrip
        ) {
            if (\ts\stringContains($hasType, $noStrip)) {
                return 'Has' . $required . $ownedHasName . $hasType;
            }
        }

        foreach (
            [
                RelationshipHelper::INTERNAL_TYPE_ONE_TO_MANY,
                RelationshipHelper::INTERNAL_TYPE_MANY_TO_ONE,
            ] as $stripAll
        ) {
            if (\ts\stringContains($hasType, $stripAll)) {
                return str_replace(
                    [
                        RelationshipHelper::PREFIX_OWNING,
                        RelationshipHelper::PREFIX_INVERSE,
                    ],
                    '',
                    'Has' . $required . $ownedHasName . $hasType
                );
            }
        }

        return str_replace(
            [
                RelationshipHelper::PREFIX_INVERSE,
            ],
            '',
            'Has' . $required . $ownedHasName . $hasType
        );
    }

    public function getFactoryFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return $this->tidy(
            str_replace(
                '\\' . DoctrineStaticMeta::ENTITIES_FOLDER_NAME . '\\',
                '\\' . DoctrineStaticMeta::ENTITY_FACTORIES_NAMESPACE . '\\',
                $entityFqn
            ) . 'Factory'
        );
    }

    public function getDtoFactoryFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return $this->tidy(
            str_replace(
                '\\' . DoctrineStaticMeta::ENTITIES_FOLDER_NAME . '\\',
                '\\' . DoctrineStaticMeta::ENTITY_FACTORIES_NAMESPACE . '\\',
                $entityFqn
            ) . 'DtoFactory'
        );
    }

    public function getRepositoryqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return $this->tidy(
            str_replace(
                '\\' . DoctrineStaticMeta::ENTITIES_FOLDER_NAME . '\\',
                '\\' . DoctrineStaticMeta::ENTITY_REPOSITORIES_NAMESPACE . '\\',
                $entityFqn
            ) . 'Repository'
        );
    }

    /**
     * @param string $ownedEntityFqn
     * @param string $srcOrTestSubFolder
     * @param string $projectRootNamespace
     *
     * @return string
     * @throws DoctrineStaticMetaException
     */
    public function getReciprocatedHasName(
        string $ownedEntityFqn,
        string $srcOrTestSubFolder,
        string $projectRootNamespace
    ): string {
        $this->assertNotCompound($ownedEntityFqn);
        $parsedFqn = $this->parseFullyQualifiedName(
            $ownedEntityFqn,
            $srcOrTestSubFolder,
            $projectRootNamespace
        );

        $subDirectories = $parsedFqn[2];

        return $this->getSingularNamespacedName($ownedEntityFqn, $subDirectories);
    }

    /**
     * Get the Fully Qualified Namespace for the Relation Interface for a specific Entity and hasType
     *
     * @param string      $hasType
     * @param string      $ownedEntityFqn
     * @param string|null $projectRootNamespace
     * @param string      $srcFolder
     *
     * @return string
     * @throws DoctrineStaticMetaException
     */
    public function getOwningInterfaceFqn(
        string $hasType,
        string $ownedEntityFqn,
        string $projectRootNamespace = null,
        string $srcFolder = DoctrineStaticMeta::DEFAULT_SRC_SUBFOLDER
    ): string {
        $this->assertNotCompound($ownedEntityFqn);
        try {
            $ownedHasName = $this->getOwnedHasName($hasType, $ownedEntityFqn, $srcFolder, $projectRootNamespace);
            if (null === $projectRootNamespace) {
                $projectRootNamespace = $this->getProjectRootNamespaceFromComposerJson($srcFolder);
            }
            [$ownedClassName, , $ownedSubDirectories] = $this->parseFullyQualifiedName(
                $ownedEntityFqn,
                $srcFolder,
                $projectRootNamespace
            );
            $interfaceSubDirectories = array_slice($ownedSubDirectories, 2);
            $owningInterfaceFqn      = $this->getOwningRelationsRootFqn(
                $projectRootNamespace,
                $interfaceSubDirectories
            );
            $required                = \ts\stringContains($hasType, RelationshipHelper::PREFIX_REQUIRED)
                ? 'Required'
                : '';
            $owningInterfaceFqn      .= '\\' .
                                        $ownedClassName .
                                        '\\Interfaces\\Has' .
                                        $required .
                                        $ownedHasName .
                                        'Interface';

            return $this->tidy($owningInterfaceFqn);
        } catch (Exception $e) {
            throw new DoctrineStaticMetaException(
                'Exception in ' . __METHOD__ . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getEntityInterfaceFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return str_replace(
                   '\\Entities\\',
                   '\\Entity\\Interfaces\\',
                   $entityFqn
               ) . 'Interface';
    }

    private function assertNotCompound(string ...$fqns): void
    {
        $errors = [];
        foreach ($fqns as $fqn) {
            if (str_contains($fqn, '|') === false) {
                continue;
            }
            $errors[] = 'FQN ' . $fqn . ' is a compound type';
        }
        if ([] === $errors) {
            return;
        }
        throw new \InvalidArgumentException(implode("\n", $errors));
    }

    public function getEntityFqnFromEntityInterfaceFqn(string $entityInterfaceFqn): string
    {
        $this->assertNotCompound($entityInterfaceFqn);

        return substr(
            str_replace(
                '\\Entity\\Interfaces\\',
                '\\Entities\\',
                $entityInterfaceFqn
            ),
            0,
            -strlen('Interface')
        );
    }

    public function getEntityFqnFromEntityFactoryFqn(string $entityFactoryFqn): string
    {
        $this->assertNotCompound($entityFactoryFqn);

        return substr(
            str_replace(
                '\\Entity\\Factories\\',
                '\\Entities\\',
                $entityFactoryFqn
            ),
            0,
            -strlen('Factory')
        );
    }

    public function getEntityFqnFromEntityDtoFactoryFqn(string $entityDtoFactoryFqn): string
    {
        $this->assertNotCompound($entityDtoFactoryFqn);

        return substr(
            str_replace(
                '\\Entity\\Factories\\',
                '\\Entities\\',
                $entityDtoFactoryFqn
            ),
            0,
            -strlen('DtoFactory')
        );
    }

    public function getEntityDtoFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return str_replace(
                   '\\Entities\\',
                   '\\Entity\\DataTransferObjects\\',
                   $entityFqn
               ) . 'Dto';
    }

    /**
     * @param string $entityDtoFqn
     *
     * @return string
     * @deprecated please use the static method on the DTO directly
     *
     */
    public function getEntityFqnFromEntityDtoFqn(string $entityDtoFqn): string
    {
        $this->assertNotCompound($entityDtoFqn);

        return $entityDtoFqn::getEntityFqn();
    }

    public function getEntityFqnFromEntityRepositoryFqn(string $entityRepositoryFqn): string
    {
        $this->assertNotCompound($entityRepositoryFqn);

        return substr(
            str_replace(
                '\\Entity\\Repositories\\',
                '\\Entities\\',
                $entityRepositoryFqn
            ),
            0,
            -strlen('Repository')
        );
    }

    public function getEntityFqnFromEntitySaverFqn(string $entitySaverFqn): string
    {
        $this->assertNotCompound($entitySaverFqn);

        return substr(
            str_replace(
                '\\Entity\\Savers\\',
                '\\Entities\\',
                $entitySaverFqn
            ),
            0,
            -strlen('Saver')
        );
    }

    public function getEntitySaverFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return str_replace(
                   '\\Entities\\',
                   '\\Entity\\Savers\\',
                   $entityFqn
               ) . 'Saver';
    }

    public function getEntityFqnFromEntityUpserterFqn(string $entityUpserterFqn): string
    {
        $this->assertNotCompound($entityUpserterFqn);

        return substr(
            str_replace(
                '\\Entity\\Savers\\',
                '\\Entities\\',
                $entityUpserterFqn
            ),
            0,
            -strlen('Upserter')
        );
    }

    public function getEntityUpserterFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return str_replace(
                   '\\Entities\\',
                   '\\Entity\\Savers\\',
                   $entityFqn
               ) . 'Upserter';
    }

    public function getEntityFqnFromEntityUnitOfWorkHelperFqn(string $entityUnitofWorkHelperFqn): string
    {
        $this->assertNotCompound($entityUnitofWorkHelperFqn);

        return substr(
            str_replace(
                '\\Entity\\Savers\\',
                '\\Entities\\',
                $entityUnitofWorkHelperFqn
            ),
            0,
            -strlen('UnitOfWorkHelper')
        );
    }

    public function getEntityUnitOfWorkHelperFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return str_replace(
                   '\\Entities\\',
                   '\\Entity\\Savers\\',
                   $entityFqn
               ) . 'UnitOfWorkHelper';
    }

    public function getEntityFqnFromEntityTestFqn(string $entityTestFqn): string
    {
        $this->assertNotCompound($entityTestFqn);

        return substr(
            $entityTestFqn,
            0,
            -strlen('Test')
        );
    }

    public function getEntityTestFqnFromEntityFqn(string $entityFqn): string
    {
        $this->assertNotCompound($entityFqn);

        return $entityFqn . 'Test';
    }
}
