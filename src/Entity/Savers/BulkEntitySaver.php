<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Savers;

class BulkEntitySaver extends AbstractBulkProcess
{
    protected function doSave(): void
    {
        if ([] === $this->entitiesToSave) {
            return;
        }
        foreach ($this->entitiesToSave as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }
}
