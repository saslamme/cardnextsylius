<?php

declare(strict_types=1);

namespace App\Repository\Pricing;

use App\Entity\Pricing\VariantTierPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Core\Model\ProductVariantInterface;

/** @extends ServiceEntityRepository<VariantTierPrice> */
class VariantTierPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, VariantTierPrice::class); }

    public function findApplicableTier(ProductVariantInterface $variant, string $channelCode, int $quantity): ?VariantTierPrice
    {
        $result = $this->createQueryBuilder('tier')
            ->andWhere('tier.variant = :variant')->andWhere('tier.channelCode = :channel')
            ->andWhere('tier.minQuantity <= :quantity')
            ->setParameter('variant', $variant)->setParameter('channel', $channelCode)->setParameter('quantity', max(1, $quantity))
            ->orderBy('tier.minQuantity', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();

        return $result instanceof VariantTierPrice ? $result : null;
    }

    /** @return list<VariantTierPrice> */
    public function findForVariantAndChannel(ProductVariantInterface $variant, string $channelCode): array
    {
        return $this->findBy(['variant' => $variant, 'channelCode' => $channelCode], ['minQuantity' => 'ASC']);
    }
}
