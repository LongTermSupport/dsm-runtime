<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Fields\Traits\String;

// phpcs:disable

use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use LTS\DsmRuntime\Entity\Fields\Interfaces\String\IsbnFieldInterface;
use LTS\DsmRuntime\MappingHelper;
use LTS\DsmRuntime\Schema\Database;
use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetaData;

// phpcs:enable
trait IsbnFieldTrait
{

    /**
     * @var string|null
     */
    private $isbn;

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @param ClassMetadataBuilder $builder
     */
    public static function metaForIsbn(ClassMetadataBuilder $builder): void
    {
        MappingHelper::setSimpleStringFields(
            [IsbnFieldInterface::PROP_ISBN],
            $builder,
            IsbnFieldInterface::DEFAULT_ISBN,
            true
        );
    }

    /**
     * This method sets the validation for this field.
     *
     * You should add in as many relevant property constraints as you see fit.
     *
     * @param ValidatorClassMetaData $metadata
     *
     * @throws MissingOptionsException
     * @throws InvalidOptionsException
     * @throws ConstraintDefinitionException
     */
    protected static function validatorMetaForPropertyIsbn(ValidatorClassMetaData $metadata): void
    {
        $metadata->addPropertyConstraints(
            IsbnFieldInterface::PROP_ISBN,
            [
                new Isbn(),
                new Length(
                    [
                        'min' => 0,
                        'max' => Database::MAX_VARCHAR_LENGTH,
                    ]
                ),
            ]
        );
    }

    /**
     * @return string|null
     */
    public function getIsbn(): ?string
    {
        if (null === $this->isbn) {
            return IsbnFieldInterface::DEFAULT_ISBN;
        }

        return $this->isbn;
    }

    /**
     * @param string|null $isbn
     *
     * @return self
     */
    private function setIsbn(?string $isbn): self
    {
        $this->updatePropertyValue(
            IsbnFieldInterface::PROP_ISBN,
            $isbn
        );

        return $this;
    }
}
