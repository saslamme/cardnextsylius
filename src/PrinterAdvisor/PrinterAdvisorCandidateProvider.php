<?php

declare(strict_types=1);

namespace App\PrinterAdvisor;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class PrinterAdvisorCandidateProvider
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<PrinterAdvisorCandidate> */
    public function forChannel(ChannelInterface $channel): array
    {
        /** @var list<Product> $products */
        $products = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT product, profile, variants, pricing, images, manufacturer, translations')
            ->from(Product::class, 'product')
            ->innerJoin('product.printerAdvisorProfile', 'profile', 'WITH', 'profile.enabled = true')
            ->innerJoin('product.channels', 'channel', 'WITH', 'channel = :channel')
            ->innerJoin('product.variants', 'variants', 'WITH', 'variants.enabled = true')
            ->innerJoin('variants.channelPricings', 'pricing', 'WITH', 'pricing.channelCode = :channelCode AND pricing.price IS NOT NULL')
            ->leftJoin('product.images', 'images')
            ->leftJoin('product.manufacturer', 'manufacturer')
            ->leftJoin('product.translations', 'translations')
            ->andWhere('product.enabled = true')
            ->andWhere('product.availableOn IS NULL OR product.availableOn <= :now')
            ->setParameter('channel', $channel)
            ->setParameter('channelCode', $channel->getCode())
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('profile.priority', 'DESC')
            ->getQuery()->getResult();

        $result = [];
        foreach ($products as $product) {
            $prices = [];
            foreach ($product->getEnabledVariants() as $variant) {
                if (!$variant instanceof ProductVariant) {
                    continue;
                }
                $pricing = $variant->getChannelPricingForChannel($channel);
                if ($pricing?->getPrice() !== null) {
                    $prices[] = $pricing->getPrice();
                }
            }
            if ($prices !== [] && $product->getPrinterAdvisorProfile() !== null) {
                $result[] = new PrinterAdvisorCandidate($product, $product->getPrinterAdvisorProfile(), min($prices));
            }
        }

        return $result;
    }
}
