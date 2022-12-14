<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Traits;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Proxy\Proxy;
use LTS\DsmRuntime\DoctrineStaticMeta;
use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use Ramsey\Uuid\UuidInterface;
use ReflectionException;

trait JsonSerializableTrait
{
    /**
     * @return array
     * @throws ReflectionException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function jsonSerialize(): array
    {
        $dsm         = static::getDoctrineStaticMeta();
        $toSerialize = [];
        $getters     = $dsm->getGetters();
        foreach ($getters as $getter) {
            /** @var mixed $got */
            $got = $this->$getter();
            if ($got instanceof EntityInterface) {
                continue;
            }
            if ($got instanceof Collection) {
                continue;
            }
            if ($got instanceof Proxy) {
                continue;
            }
            if ($got instanceof UuidInterface) {
                $got = $got->toString();
            }
            if ($got instanceof DateTimeImmutable) {
                $got = $got->format('Y-m-d H:i:s');
            }
            if (method_exists($got, '__toString')) {
                $got = (string)$got;
            }
            if (null !== $got && false === is_scalar($got)) {
                continue;
            }
            $property               = $dsm->getPropertyNameFromGetterName($getter);
            $toSerialize[$property] = $got;
        }

        return $toSerialize;
    }

    abstract public static function getDoctrineStaticMeta(): DoctrineStaticMeta;
}
