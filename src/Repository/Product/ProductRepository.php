<?php

declare(strict_types=1);

namespace App\Repository\Product;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ProductRepository as BaseProductRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class ProductRepository extends BaseProductRepository
{
    public function createListQueryBuilder(string $locale, mixed $taxonId = null): QueryBuilder
    {
        return parent::createListQueryBuilder($locale, $taxonId)
            ->andWhere('o.addonOnly = :cardnextAddonOnly')
            ->setParameter('cardnextAddonOnly', false);
    }

    public function createShopListQueryBuilder(ChannelInterface $channel, TaxonInterface $taxon, string $locale, array $sorting = [], bool $includeAllDescendants = false): QueryBuilder
    {
        return parent::createShopListQueryBuilder($channel, $taxon, $locale, $sorting, $includeAllDescendants)
            ->andWhere('o.addonOnly = :cardnextAddonOnly')
            ->setParameter('cardnextAddonOnly', false);
    }

    public function findLatestByChannel(ChannelInterface $channel, string $locale, int $count): array
    {
        return array_values(array_filter(parent::findLatestByChannel($channel, $locale, $count), static fn ($product): bool => !$product->isAddonOnly()));
    }
}
