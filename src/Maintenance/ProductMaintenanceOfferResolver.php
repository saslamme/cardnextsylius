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

    public function __construct(private ChannelContextInterface $channelContext)
    {
    }

    /** @return list<MaintenanceOffer> */
    public function resolve(Product $product): array
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
                foreach ($associatedProduct->getVariants() as $variant) {
                    if (!$variant instanceof ProductVariant || !$variant->isEnabled()) {
                        continue;
                    }
                    $pricing = $variant->getChannelPricingForChannel($channel);
                    if ($pricing === null || $pricing->getPrice() === null) {
                        continue;
                    }
                    $offers[] = new MaintenanceOffer($associatedProduct, $variant, $pricing->getPrice(), (string) $channel->getBaseCurrency()?->getCode());

                    break;
                }
            }
        }

        return $offers;
    }

    public function findValidVariant(Product $mainProduct, int|string $variantId): ?ProductVariant
    {
        foreach ($this->resolve($mainProduct) as $offer) {
            if ((string) $offer->variant->getId() === (string) $variantId || $offer->variant->getCode() === (string) $variantId) {
                return $offer->variant;
            }
        }

        return null;
    }
}
