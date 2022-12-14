<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Fields\FakerData\String;

use LTS\DsmRuntime\Entity\Fields\FakerData\AbstractFakerDataProvider;
use Symfony\Component\Intl\Locales;

class LocaleIdentifierFakerData extends AbstractFakerDataProvider
{
    public function __invoke(): string
    {
        do {
            $value = $this->generator->locale;
        } while (false === $this->isValid($value));

        return $value;
    }

    private function isValid(string $value): bool
    {
        return Locales::exists(\Locale::canonicalize($value));
    }
}
