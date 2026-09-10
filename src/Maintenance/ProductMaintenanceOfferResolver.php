<?php

declare(strict_types=1);

namespace App\Maintenance;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class ProductMaintenanceOfferResolver
{
    public const ASSOCIATION_TYPE = 'maintenance_contracts';

    public function __construct(private ChannelContextInterface $channelContext, private WertgarantieVariantResolver $wertgarantieResolver)
    {
    }

    /** @return list<MaintenanceOffer> */
    public function resolve(Product $product, ?ProductVariant $mainVariant = null): array
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) {
            return [];
        }
        $offers = [];
        foreach ($product->getAssociations() as $association) {
            if ($association->getType()?->getCode() !== self::ASSOCIATION_TYPE) {
                continue;
            }
            foreach ($association->getAssociatedProducts() as $associatedProduct) {
                if (!$associatedProduct instanceof Product || !$associatedProduct->isAddonOnly() || !$associatedProduct->isEnabled() || !$associatedProduct->hasChannel($channel)) {
                    continue;
                }
                if ($this->wertgarantieResolver->isWertgarantie($associatedProduct)) {
                    if (!$mainVariant instanceof ProductVariant) {
                        continue;
                    }
                    $variant = $this->wertgarantieResolver->resolve($associatedProduct, $mainVariant, $channel);
                    if ($variant instanceof ProductVariant) {
                        $offers[] = new MaintenanceOffer($associatedProduct, $variant, (int) $variant->getChannelPricingForChannel($channel)?->getPrice(), (string) $channel->getBaseCurrency()?->getCode(), MaintenanceOffer::CATEGORY_WARRANTY);
                    }

                    continue;
                }
                foreach ($associatedProduct->getVariants() as $variant) {
                    if (!$variant instanceof ProductVariant || !$variant->isEnabled()) {
                        continue;
                    }
                    $pricing = $variant->getChannelPricingForChannel($channel);
                    if ($pricing === null || $pricing->getPrice() === null) {
                        continue;
                    }
                    $offers[] = new MaintenanceOffer($associatedProduct, $variant, $pricing->getPrice(), (string) $channel->getBaseCurrency()?->getCode(), MaintenanceOffer::CATEGORY_SERVICE);

                    break;
                }
            }
        }

        return $offers;
    }

    public function findValidVariant(Product $mainProduct, ProductVariant $mainVariant, int|string $variantId, string $category): ?ProductVariant
    {
        $offers = $this->resolve($mainProduct, $mainVariant);
        foreach ($offers as $offer) {
            if ($offer->category === $category && ((string) $offer->variant->getId() === (string) $variantId || $offer->variant->getCode() === (string) $variantId)) {
                return $offer->variant;
            }
        }

        // A LiveComponent variant switch may submit the previous price-tier ID.
        // Retain the selected K3/K5 tariff, but always return its freshly resolved tier.
        if ($category === MaintenanceOffer::CATEGORY_WARRANTY) {
            foreach ($mainProduct->getAssociations() as $association) {
                if ($association->getType()?->getCode() !== self::ASSOCIATION_TYPE) {
                    continue;
                }
                foreach ($association->getAssociatedProducts() as $product) {
                    if (!$product instanceof Product || !$this->wertgarantieResolver->isWertgarantie($product)) {
                        continue;
                    }
                    foreach ($product->getVariants() as $oldTier) {
                        if (!$oldTier instanceof ProductVariant || ((string) $oldTier->getId() !== (string) $variantId && $oldTier->getCode() !== (string) $variantId)) {
                            continue;
                        }
                        foreach ($offers as $offer) {
                            if ($offer->category === MaintenanceOffer::CATEGORY_WARRANTY && $offer->product === $product) {
                                return $offer->variant;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}
