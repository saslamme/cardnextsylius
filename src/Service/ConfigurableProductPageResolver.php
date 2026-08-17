<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ConfigurableProductPageResolver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function resolve(string $path, string $locale, Channel $channel): ?Product
    {
        /** @var Product|null $product */
        $product = $this->entityManager->createQueryBuilder()
            ->select('product', 'translation', 'configurator')
            ->from(Product::class, 'product')
            ->innerJoin('product.translations', 'translation')
            ->innerJoin('product.channels', 'channel', 'WITH', 'channel = :channel')
            ->innerJoin('product.configurator', 'configurator', 'WITH', 'configurator.enabled = true')
            ->andWhere('translation.locale = :locale')
            ->andWhere('translation.configuratorPath = :path')
            ->andWhere('product.enabled = true')
            ->andWhere('product.productKind = :kind')
            ->setParameters(['channel' => $channel, 'locale' => $locale, 'path' => trim($path, '/'), 'kind' => 'configurable'])
            ->getQuery()
            ->getOneOrNullResult();

        if ($product !== null) {
            $product->setCurrentLocale($locale);
        }

        return $product;
    }
}
