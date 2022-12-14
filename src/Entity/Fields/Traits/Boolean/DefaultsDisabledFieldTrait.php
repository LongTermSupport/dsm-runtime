<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Fields\Traits\Boolean;

use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;
use LTS\DsmRuntime\Entity\Fields\Interfaces\Boolean\DefaultsDisabledFieldInterface;
use LTS\DsmRuntime\MappingHelper;

trait DefaultsDisabledFieldTrait
{

    /**
     * @var bool
     */
    private $defaultsDisabled = DefaultsDisabledFieldInterface::DEFAULT_DEFAULTS_DISABLED;

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @param ClassMetadataBuilder $builder
     */
    public static function metaForDefaultsDisabled(ClassMetadataBuilder $builder): void
    {
        MappingHelper::setSimpleBooleanFields(
            [DefaultsDisabledFieldInterface::PROP_DEFAULTS_DISABLED],
            $builder,
            DefaultsDisabledFieldInterface::DEFAULT_DEFAULTS_DISABLED
        );
    }

    /**
     * @return bool
     */
    public function isDefaultsDisabled(): bool
    {
        return $this->defaultsDisabled;
    }

    /**
     * @param bool $defaultsDisabled
     *
     * @return self
     */
    private function setDefaultsDisabled(bool $defaultsDisabled): self
    {
        $this->updatePropertyValue(
            DefaultsDisabledFieldInterface::PROP_DEFAULTS_DISABLED,
            $defaultsDisabled
        );

        return $this;
    }

    private function initDefaultDisabled(): void
    {
        $this->defaultsDisabled = DefaultsDisabledFieldInterface::DEFAULT_DEFAULTS_DISABLED;
    }
}
