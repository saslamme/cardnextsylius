<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Entity\Configurator\Configurator;
use Doctrine\ORM\EntityManagerInterface;

final class ConfiguratorAggregateDeleter
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function delete(Configurator $configurator): void
    {
        $this->entityManager->wrapInTransaction(function () use ($configurator): void {
            $this->entityManager->remove($configurator);
        });
    }
}
