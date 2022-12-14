<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Helper;

use LTS\DsmRuntime\DoctrineStaticMeta;
use ReflectionException;
use RuntimeException;
use ts\Reflection\ReflectionClass;
use function array_slice;
use function str_replace;

final class ReflectionHelper
{

    public function __construct(private NamespaceHelper $namespaceHelper, private TypeHelper $typeHelper)
    {
    }

    /**
     * @param ReflectionClass $fieldTraitReflection
     *
     * @return string
     */
    public function getFakerProviderFqnFromFieldTraitReflection(ReflectionClass $fieldTraitReflection): string
    {
        return str_replace(
            [
                '\\Traits\\',
                'FieldTrait',
            ],
            [
                '\\FakerData\\',
                'FakerData',
            ],
            $fieldTraitReflection->getName()
        );
    }

    /**
     * Work out the entity namespace root from a single entity reflection object.
     *
     * @param ReflectionClass $entityReflection
     *
     * @return string
     */
    public function getEntityNamespaceRootFromEntityReflection(
        ReflectionClass $entityReflection
    ): string {
        return $this->namespaceHelper->tidy(
            $this->namespaceHelper->getNamespaceRootToDirectoryFromFqn(
                $entityReflection->getName(),
                DoctrineStaticMeta::ENTITIES_FOLDER_NAME
            )
        );
    }

    /**
     * Find which trait is implementing a method in a class,
     * If it is not found in a trait, we return the class itself as it must be there
     *
     * @param ReflectionClass $reflectionClass
     * @param string          $methodName
     *
     * @return ReflectionClass
     * @throws ReflectionException
     */
    public function getTraitImplementingMethod(ReflectionClass $reflectionClass, string $methodName): ?ReflectionClass
    {
        $traitsWithMethod = [];
        foreach ($reflectionClass->getTraits() as $trait) {
            try {
                $trait->getMethod($methodName);
                $traitsWithMethod[] = $trait;
            } catch (ReflectionException $e) {
                continue;
            }
        }
        if (count($traitsWithMethod) > 1) {
            throw new RuntimeException(
                'Found more than one trait implementing the method ' . $methodName . ' in ' .
                $reflectionClass->getShortName()
            );
        }
        if ([] === $traitsWithMethod) {
            return null;
        }

        return current($traitsWithMethod);
    }

    /**
     * Find which trait is implementing a method in a class
     *
     * @param ReflectionClass $class
     * @param string          $propertyName
     *
     * @return ReflectionClass
     * @throws ReflectionException
     */
    public function getTraitProvidingProperty(ReflectionClass $class, string $propertyName): ReflectionClass
    {
        $traitsWithProperty = [];
        foreach ($class->getTraits() as $trait) {
            try {
                $trait->getProperty($propertyName);
                $traitsWithProperty[] = $trait;
            } catch (ReflectionException $e) {
                continue;
            }
        }
        if ([] === $traitsWithProperty) {
            if ($class->isTrait() && $class->hasProperty($propertyName)) {
                return $class;
            }
            throw new RuntimeException(
                'Failed finding trait providing the property ' . $propertyName . ' in ' .
                $class->getShortName()
            );
        }
        if (count($traitsWithProperty) > 1) {
            throw new RuntimeException(
                'Found more than one trait providing the property ' . $propertyName . ' in ' .
                $class->getShortName()
            );
        }

        return current($traitsWithProperty);
    }

    /**
     * Get the full method body using reflection
     *
     * @param string          $methodName
     * @param ReflectionClass $reflectionClass
     *
     * @return string
     */
    public function getMethodBody(string $methodName, ReflectionClass $reflectionClass): string
    {
        $method      = $reflectionClass->getMethod($methodName);
        $startLine   = $method->getStartLine() - 1;
        $length      = $method->getEndLine() - $startLine;
        $lines       = file($reflectionClass->getFileName());
        $methodLines = array_slice($lines, $startLine, $length);

        return implode('', $methodLines);
    }

    /**
     * @param ReflectionClass $reflectionClass
     *
     * @return array|string[]
     */
    public function getUseStatements(ReflectionClass $reflectionClass): array
    {
        $content = \ts\file_get_contents($reflectionClass->getFileName());
        preg_match_all('%^use.+?;%m', $content, $matches);

        return $matches[0];
    }

    public function getUseStatementForShortName(string $shortName, ReflectionClass $reflectionClass): ?string
    {
        if (!$this->typeHelper->isImportableType($shortName)) {
            return null;
        }
        $stmtsToMerge   = [];
        $stmtsToMerge[] = $this->getUseStatements($reflectionClass);
        foreach ($reflectionClass->getTraits() as $trait) {
            $stmtsToMerge[] = $this->getUseStatements($trait);
        }
        $stmts = array_merge(...$stmtsToMerge);
        foreach ($stmts as $stmt) {
            if (str_ends_with($stmt, "$shortName;")) {
                return $stmt;
            }
        }
        throw new \RuntimeException(
            'Failed finding use statement for shortName ' . $shortName .
            ' in use statements: ' . print_r($stmts, true) . ' from ' . $reflectionClass->getName()
        );
    }
}
