<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Fields\Traits\String;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use Doctrine\ORM\Mapping\Builder\FieldBuilder;
use LTS\DsmRuntime\Entity\Fields\Interfaces\String\UnicodeLanguageIdentifierFieldInterface;
use LTS\DsmRuntime\MappingHelper;
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Mapping\ClassMetadata as ValidatorClassMetaData;

trait UnicodeLanguageIdentifierFieldTrait
{

    /**
     * @var string|null
     */
    private $unicodeLanguageIdentifier;

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @param ClassMetadataBuilder $builder
     */
    public static function metaForUnicodeLanguageIdentifier(ClassMetadataBuilder $builder): void
    {
        $fieldBuilder = new FieldBuilder(
            $builder,
            [
                'fieldName' => UnicodeLanguageIdentifierFieldInterface::PROP_UNICODE_LANGUAGE_IDENTIFIER,
                'type'      => Type::STRING,
                'default'   => null,
            ]
        );
        $fieldBuilder
            ->columnName(MappingHelper::getColumnNameForField(
                UnicodeLanguageIdentifierFieldInterface::PROP_UNICODE_LANGUAGE_IDENTIFIER
            ))
            ->nullable()
            ->unique(false)
            ->length(50)
            ->build();
    }


    /**
     * Validates the property is a Unicode Language Identifier
     *
     * @param ValidatorClassMetaData $metadata
     */
    protected static function validatorMetaForPropertyUnicodeLanguageIdentifier(ValidatorClassMetaData $metadata): void
    {
        $metadata->addPropertyConstraints(
            UnicodeLanguageIdentifierFieldInterface::PROP_UNICODE_LANGUAGE_IDENTIFIER,
            [
                new Language(),
                new Length(
                    [
                        'min' => 0,
                        'max' => 50,
                    ]
                ),
            ]
        );
    }

    /**
     * @return string|null
     */
    public function getUnicodeLanguageIdentifier(): ?string
    {
        if (null === $this->unicodeLanguageIdentifier) {
            return UnicodeLanguageIdentifierFieldInterface::DEFAULT_UNICODE_LANGUAGE_IDENTIFIER;
        }

        return $this->unicodeLanguageIdentifier;
    }

    /**
     * @param string|null $unicodeLanguageIdentifier
     *
     * @return self
     */
    private function setUnicodeLanguageIdentifier(?string $unicodeLanguageIdentifier): self
    {
        $this->updatePropertyValue(
            UnicodeLanguageIdentifierFieldInterface::PROP_UNICODE_LANGUAGE_IDENTIFIER,
            $unicodeLanguageIdentifier
        );

        return $this;
    }
}
