<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Entity\Pricing\VariantTierPrice;
use App\Repository\Pricing\VariantTierPriceRepository;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

class VariantTierPriceResolver
{
    public function __construct(private VariantTierPriceRepository $repository) {}

    public function resolve(ProductVariantInterface $variant, ChannelInterface $channel, int $quantity): ?int
    {
        return $this->resolveTier($variant, $channel, $quantity)?->getPrice();
    }

    public function resolveTier(ProductVariantInterface $variant, ChannelInterface $channel, int $quantity): ?VariantTierPrice
    {
        return $this->repository->findApplicableTier($variant, (string) $channel->getCode(), max(1, $quantity));
    }

    /** @return list<VariantTierPrice> */
    public function tiers(ProductVariantInterface $variant, ChannelInterface $channel): array
    {
        return $this->repository->findForVariantAndChannel($variant, (string) $channel->getCode());
    }
}
