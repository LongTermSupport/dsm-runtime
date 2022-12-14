<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Interfaces;

use Doctrine\Persistence\PropertyChangedListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\NotifyPropertyChanged;

interface ImplementNotifyChangeTrackingPolicyInterface extends NotifyPropertyChanged
{
    public function addPropertyChangedListener(PropertyChangedListener $listener): void;

    public function removePropertyChangedListeners();

    public function notifyEmbeddablePrefixedProperties(
        string $embeddablePropertyName,
        ?string $propName = null,
        $oldValue = null,
        $newValue = null
    ): void;

    public function ensureMetaDataIsSet(EntityManagerInterface $entityManager): void;
}
