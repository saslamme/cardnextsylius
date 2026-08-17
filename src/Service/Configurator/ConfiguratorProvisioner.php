<?php

declare(strict_types=1);

namespace App\Service\Configurator;

use App\Entity\Configurator\Configurator;
use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;

final class ConfiguratorProvisioner
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function ensureConfigurator(Product $product): ?Configurator
    {
        if (!$product->isConfigurable()) {
            return null;
        }
        if ($product->getConfigurator() !== null) {
            return $product->getConfigurator();
        }

        $existing = $product->getId() === null ? null : $this->entityManager
            ->getRepository(Configurator::class)
            ->findOneBy(['product' => $product]);
        if ($existing instanceof Configurator) {
            $product->attachConfigurator($existing);

            return $existing;
        }

        $productCode = (string) $product->getCode();
        if ($productCode === '') {
            throw new \DomainException('Ein Konfigurationsprodukt benötigt vor der Anlage einen Produktcode.');
        }
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($productCode)), '_') ?: 'product';
        $code = sprintf('%s_%s', substr($base, 0, 82), substr(hash('sha256', $productCode), 0, 12));
        $configurator = new Configurator($code, sprintf('Konfigurator %s', $productCode));
        $product->attachConfigurator($configurator);
        $this->entityManager->persist($configurator);

        return $configurator;
    }
}
