<?php

declare(strict_types=1);

namespace App\Maintenance;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Service\B2BPriceResolver;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;

final readonly class WertgarantieVariantResolver
{
    public const KOMFORT_3 = 'WERTGARANTIE_KOMFORT_3';

    public const KOMFORT_5 = 'WERTGARANTIE_KOMFORT_5';

    /** @var list<int> */
    public const LIMITS = [20000, 30000, 40000, 50000, 70000, 100000, 130000, 150000, 200000, 300000, 350000, 500000, 750000, 1000000];

    /** @var array<string, list<int>> */
    public const PRICES = [
        self::KOMFORT_3 => [2990, 3990, 4990, 5990, 7990, 10990, 12990, 13990, 16990, 18990, 22990, 25990, 34990, 39990],
        self::KOMFORT_5 => [5990, 7990, 9990, 11990, 13990, 15990, 17990, 19990, 23990, 29990, 34990, 41990, 52990, 64990],
    ];

    public function __construct(private ?B2BPriceResolver $priceResolver = null, private ?CustomerContextInterface $customerContext = null)
    {
    }

    public function isWertgarantie(Product $product): bool
    {
        return in_array($product->getCode(), [self::KOMFORT_3, self::KOMFORT_5], true);
    }

    public function resolve(Product $tariff, ProductVariant $mainVariant, ChannelInterface $channel): ?ProductVariant
    {
        if (!$this->isWertgarantie($tariff) || !$tariff->isEnabled() || !$tariff->hasChannel($channel) || !$mainVariant->isEnabled()) {
            return null;
        }

        // Use Cardnext's central B2B purchase-price pipeline in the shop. The fallback
        // keeps the pure domain resolver usable without a customer context (e.g. tests).
        // Values are minor units throughout; no tax conversion or floats occur here.
        $devicePrice = $this->priceResolver?->resolve($mainVariant, $channel, 1, $this->customerContext?->getCustomer())
            ?? $mainVariant->getChannelPricingForChannel($channel)?->getPrice();
        if ($devicePrice === null || $devicePrice < 0) {
            return null;
        }

        $limit = null;
        foreach (self::LIMITS as $candidate) {
            if ($devicePrice <= $candidate) {
                $limit = $candidate;

                break;
            }
        }
        if ($limit === null) {
            return null;
        }

        $prefix = $tariff->getCode() === self::KOMFORT_3 ? 'WERTGARANTIE_K3_' : 'WERTGARANTIE_K5_';
        $code = $prefix . sprintf($limit === 1000000 ? '%d' : '%04d', intdiv($limit, 100));
        foreach ($tariff->getVariants() as $variant) {
            if ($variant instanceof ProductVariant && $variant->getCode() === $code && $variant->isEnabled() && $variant->getChannelPricingForChannel($channel)?->getPrice() !== null) {
                return $variant;
            }
        }

        return null;
    }
}
