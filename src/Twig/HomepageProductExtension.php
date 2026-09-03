<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class HomepageProductExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cardnext_homepage_products', [$this, 'getHomepageProducts']),
        ];
    }

    /**
     * @return list<Product>
     */
    public function getHomepageProducts(int $limit = 4): array
    {
        $limit = max(1, min(12, $limit));
        $channel = $this->channelContext->getChannel();

        /** @var list<Product> $products */
        $products = $this->entityManager
            ->getRepository(Product::class)
            ->createQueryBuilder('product')
            ->innerJoin('product.channels', 'channel')
            ->andWhere('channel = :channel')
            ->andWhere('product.enabled = :enabled')
            ->andWhere('product.homepageFeatured = :featured')
            ->andWhere('product.addonOnly = :addonOnly')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true)
            ->setParameter('featured', true)
            ->setParameter('addonOnly', false)
            ->orderBy('product.homepagePosition', 'ASC')
            ->addOrderBy('product.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $products;
    }
}
