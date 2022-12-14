<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Embeddable\Objects\Identity;

use Doctrine\ORM\Mapping\ClassMetadata;
use LTS\DsmRuntime\Entity\Embeddable\Interfaces\Identity\HasFullNameEmbeddableInterface;
use LTS\DsmRuntime\Entity\Embeddable\Interfaces\Objects\Identity\FullNameEmbeddableInterface;
use LTS\DsmRuntime\Entity\Embeddable\Objects\AbstractEmbeddableObject;
use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use LTS\DsmRuntime\MappingHelper;

class FullNameEmbeddable extends AbstractEmbeddableObject implements FullNameEmbeddableInterface
{
    /**
     * The title or Honorific Prefix, for example Mr, Dr
     *
     * @var string
     */
    private $title;

    /**
     * The first or given name
     *
     * @var string
     */
    private $firstName;

    /**
     * An array of middle names
     *
     * @var array
     */
    private $middleNames;

    /**
     * The last or surname
     *
     * @var string
     */
    private $lastName;

    /**
     * The honorific suffix, for example Jr
     *
     * @var string
     */
    private $suffix;

    final public function __construct(string $title, string $firstName, array $middleNames, string $lastName, string $suffix)
    {
        $this->setTitle($title);
        $this->setFirstName($firstName);
        $this->setMiddleNames($middleNames);
        $this->setLastName($lastName);
        $this->setSuffix($suffix);
    }

    /**
     * @param string $title
     *
     * @return FullNameEmbeddableInterface
     */
    private function setTitle(string $title): FullNameEmbeddableInterface
    {
        $this->notifyEmbeddablePrefixedProperties(
            'title',
            $this->title,
            $title
        );
        $this->title = $title;

        return $this;
    }

    /**
     * @param string $firstName
     *
     * @return FullNameEmbeddableInterface
     */
    private function setFirstName(string $firstName): FullNameEmbeddableInterface
    {
        $this->notifyEmbeddablePrefixedProperties(
            'firstName',
            $this->firstName,
            $firstName
        );
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @param array $middleNames
     *
     * @return FullNameEmbeddableInterface
     */
    private function setMiddleNames(array $middleNames): FullNameEmbeddableInterface
    {
        $this->notifyEmbeddablePrefixedProperties(
            'middleNames',
            $this->middleNames,
            $middleNames
        );
        $this->middleNames = $middleNames;

        return $this;
    }

    /**
     * @param string $lastName
     *
     * @return FullNameEmbeddableInterface
     */
    private function setLastName(string $lastName): FullNameEmbeddableInterface
    {
        $this->notifyEmbeddablePrefixedProperties(
            'lastName',
            $this->lastName,
            $lastName
        );
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @param string $suffix
     *
     * @return FullNameEmbeddableInterface
     */
    private function setSuffix(string $suffix): FullNameEmbeddableInterface
    {
        $this->notifyEmbeddablePrefixedProperties(
            'suffix',
            $this->suffix,
            $suffix
        );
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * @param ClassMetadata<EntityInterface> $metadata
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public static function loadMetadata(ClassMetadata $metadata): void
    {
        $builder = self::setEmbeddableAndGetBuilder($metadata);
        MappingHelper::setSimpleFields(
            [
                FullNameEmbeddableInterface::EMBEDDED_PROP_TITLE       => MappingHelper::TYPE_STRING,
                FullNameEmbeddableInterface::EMBEDDED_PROP_FIRSTNAME   => MappingHelper::TYPE_STRING,
                FullNameEmbeddableInterface::EMBEDDED_PROP_MIDDLENAMES => MappingHelper::TYPE_ARRAY,
                FullNameEmbeddableInterface::EMBEDDED_PROP_LASTNAME    => MappingHelper::TYPE_STRING,
                FullNameEmbeddableInterface::EMBEDDED_PROP_SUFFIX      => MappingHelper::TYPE_STRING,
            ],
            $builder
        );
    }

    /**
     * @param array $properties
     */
    public static function create(array $properties): static
    {
        if (FullNameEmbeddableInterface::EMBEDDED_PROP_TITLE === key($properties)) {
            return new static(
                $properties[FullNameEmbeddableInterface::EMBEDDED_PROP_TITLE],
                $properties[FullNameEmbeddableInterface::EMBEDDED_PROP_FIRSTNAME],
                $properties[FullNameEmbeddableInterface::EMBEDDED_PROP_MIDDLENAMES],
                $properties[FullNameEmbeddableInterface::EMBEDDED_PROP_LASTNAME],
                $properties[FullNameEmbeddableInterface::EMBEDDED_PROP_SUFFIX]
            );
        }

        return new static(...array_values($properties));
    }

    /**
     * Get the full name as a single string
     *
     * @return string
     */
    public function getFormatted(): string
    {
        return $this->format(
            [
                $this->getTitle(),
                $this->getFirstName(),
                $this->format($this->middleNames),
                $this->getLastName(),
                $this->getSuffix(),
            ]
        );
    }

    /**
     * Convert the array into a single space separated list
     *
     * @param array $items
     *
     * @return string
     */
    private function format(array $items): string
    {
        return trim(
            implode(
                ' ',
                array_map(
                    'trim',
                    array_filter(
                        $items
                    )
                )
            )
        );
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName ?? '';
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName ?? '';
    }

    /**
     * @return string
     */
    public function getSuffix(): string
    {
        return $this->suffix ?? '';
    }

    public function __toString(): string
    {
        return print_r(
            [
                'fullNameEmbeddabled' => [
                    FullNameEmbeddableInterface::EMBEDDED_PROP_TITLE       => $this->getTitle(),
                    FullNameEmbeddableInterface::EMBEDDED_PROP_FIRSTNAME   => $this->getFirstName(),
                    FullNameEmbeddableInterface::EMBEDDED_PROP_MIDDLENAMES => $this->getMiddleNames(),
                    FullNameEmbeddableInterface::EMBEDDED_PROP_LASTNAME    => $this->getLastName(),
                    FullNameEmbeddableInterface::EMBEDDED_PROP_SUFFIX      => $this->getSuffix(),
                ],
            ],
            true
        );
    }

    /**
     * @return array
     */
    public function getMiddleNames(): array
    {
        return $this->middleNames ?? [];
    }

    protected function getPrefix(): string
    {
        return HasFullNameEmbeddableInterface::PROP_FULL_NAME_EMBEDDABLE;
    }
}
