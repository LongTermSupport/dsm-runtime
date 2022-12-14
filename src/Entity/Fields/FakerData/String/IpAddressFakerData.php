<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Fields\FakerData\String;

use LTS\DsmRuntime\Entity\Fields\FakerData\AbstractFakerDataProvider;

class IpAddressFakerData extends AbstractFakerDataProvider
{
    private const FORMATTERS = [
        'ipv4',
        'ipv6',
        'localIpv4',
    ];

    public function __invoke(): string
    {
        $pseudoProperty = self::FORMATTERS[array_rand(self::FORMATTERS)];
        /** @phpstan-ignore-next-line  - variable property */
        return $this->generator->$pseudoProperty;
    }
}
