<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\Entity\Savers;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LTS\DsmRuntime\Entity\Interfaces\EntityInterface;
use RuntimeException;

abstract class AbstractBulkProcess
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    protected $entitiesToSave = [];

    protected $chunkSize = 1000;

    /**
     * @var float
     */
    protected $secondsToPauseBetweenSaves = 0.0;

    /**
     * @var bool
     */
    private $gcWasEnabled;

    private $started = false;
    private $ended   = false;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->gcWasEnabled  = gc_enabled();
    }

    public function __destruct()
    {
        if (true === $this->started && false === $this->ended) {
            if (!$this->entityManager->isOpen()) {
                throw new RuntimeException('Error in ' . __METHOD__ . ': Entity Manager has been closed');
            }
            $this->endBulkProcess();
        }
    }

    public function endBulkProcess(): void
    {
        $this->started = false;
        $this->ended   = true;

        if ([] !== $this->entitiesToSave) {
            $this->doSave();
            $this->freeResources();
        }
        if (false === $this->gcWasEnabled) {
            return;
        }
        gc_enable();
    }

    abstract protected function doSave(): void;

    protected function freeResources(): void
    {
        gc_enable();
        $this->entityManager->clear();
        $this->entitiesToSave = [];
        gc_collect_cycles();
        gc_disable();
    }

    public function addEntityToSave(EntityInterface $entity): void
    {
        if (false === $this->started) {
            $this->startBulkProcess();
        }
        $this->entitiesToSave[] = $entity;
        $this->bulkSaveIfChunkBigEnough();
    }

    public function startBulkProcess(): self
    {
        gc_disable();
        $this->started = true;
        $this->ended   = false;

        return $this;
    }

    protected function bulkSaveIfChunkBigEnough(): void
    {
        $size = count($this->entitiesToSave);
        if ($size >= $this->chunkSize) {
            $this->entityManager->clear();
            $this->doSave();
            $this->freeResources();
            $this->pauseBetweenSaves();
        }
    }

    /**
     * If configured, we will pause between starting another round of saves
     */
    private function pauseBetweenSaves(): void
    {
        if (0 >= $this->secondsToPauseBetweenSaves) {
            return;
        }
        usleep((int)$this->secondsToPauseBetweenSaves * 1000000);
    }

    /**
     * This will prevent any notification on changed properties
     *
     * @param array|EntityInterface[] $entities
     *
     * @return $this
     */
    public function prepareEntitiesForBulkUpdate(array $entities): self
    {
        foreach ($entities as $entity) {
            $entity->removePropertyChangedListeners();
        }

        return $this;
    }

    public function addEntitiesToSave(array $entities)
    {
        $entitiesToSaveBackup = $this->entitiesToSave;
        $chunks               = array_chunk($entities, $this->chunkSize, true);
        foreach ($chunks as $num => $chunk) {
            $this->entitiesToSave = $chunk;
            try {
                $this->bulkSaveIfChunkBigEnough();
            } catch (DBALException $DBALException) {
                throw new \RuntimeException(
                    'Failed saving chunk ' . $num . ' of ' . count($chunks),
                    $DBALException->getCode(),
                    $DBALException
                );
            }
        }
        $this->entitiesToSave = array_merge($this->entitiesToSave, $entitiesToSaveBackup);
        $this->bulkSaveIfChunkBigEnough();
    }

    /**
     * @return int
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * @param int $chunkSize
     *
     * @return $this
     */
    public function setChunkSize(int $chunkSize): self
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    /**
     * @param float $secondsToPauseBetweenSaves
     */
    public function setSecondsToPauseBetweenSaves(float $secondsToPauseBetweenSaves): void
    {
        $this->secondsToPauseBetweenSaves = $secondsToPauseBetweenSaves;
    }
}
