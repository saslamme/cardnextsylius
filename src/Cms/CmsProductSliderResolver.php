<?php

declare(strict_types=1);

namespace App\Cms;

use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;

final readonly class CmsProductSliderResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelContextInterface $channelContext,
    ) {
    }

    /**
     * @param array<mixed> $productCodes
     *
     * @return list<Product>
     */
    public function resolve(array $productCodes, ?int $limit = null): array
    {
        $limit = max(1, min(24, $limit ?? 8));
        $codes = [];
        foreach ($productCodes as $code) {
            if (is_string($code) && ($code = trim($code)) !== '' && !isset($codes[$code])) {
                $codes[$code] = true;
            }
        }
        if ($codes === []) {
            return [];
        }

        /** @var list<Product> $products */
        $products = $this->entityManager->getRepository(Product::class)->createQueryBuilder('product')
            ->innerJoin('product.channels', 'channel')
            ->andWhere('product.code IN (:codes)')
            ->andWhere('product.enabled = :enabled')
            ->andWhere('product.addonOnly = :addonOnly')
            ->andWhere('channel = :channel')
            ->setParameter('codes', array_keys($codes))
            ->setParameter('enabled', true)
            ->setParameter('addonOnly', false)
            ->setParameter('channel', $this->channelContext->getChannel())
            ->getQuery()
            ->getResult();

        $byCode = [];
        foreach ($products as $product) {
            $byCode[(string) $product->getCode()] = $product;
        }

        $ordered = [];
        foreach (array_keys($codes) as $code) {
            if (isset($byCode[$code])) {
                $ordered[] = $byCode[$code];
                if (count($ordered) === $limit) {
                    break;
                }
            }
        }

        return $ordered;
    }
}
