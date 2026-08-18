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
        $product = $configurator->getProduct();
        if ($product !== null && !$product->isConfigurable()) {
            throw new \DomainException('Ein mit einem Standardprodukt verknüpfter Konfigurator kann nicht gelöscht werden.');
        }

        $this->entityManager->wrapInTransaction(function () use ($configurator, $product): void {
            if ($product !== null) {
                // Product owns the aggregate lifecycle. Cascade remove deletes the configurator first;
                // its database foreign keys remove only configurator-owned child records.
                $this->entityManager->remove($product);

                return;
            }

            $this->entityManager->remove($configurator);
        });
    }
}
