<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Fields\Interfaces\String;

interface EmailAddressFieldInterface
{
    public const PROP_EMAIL_ADDRESS = 'emailAddress';

    public const DEFAULT_EMAIL_ADDRESS = null;

    public function getEmailAddress(): ?string;
}
