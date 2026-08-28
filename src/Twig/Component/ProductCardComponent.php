<?php

declare(strict_types=1);

namespace App\Twig\Component;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Price-tolerant product card for Cardnext catalog listings.
 *
 * Sylius' generic card eagerly evaluates all #[ExposeInTemplate] methods. That
 * includes hasDiscount(), which calls the price calculator before the custom
 * card template starts rendering. This component deliberately resolves only
 * the variant and its channel pricing; the price component is mounted by Twig
 * only when a non-null price is available.
 */
#[AsTwigComponent(name: 'cardnext:product:card', template: 'shop/category/product_card.html.twig')]
final class ProductCardComponent
{
    #[ExposeInTemplate]
    public ProductInterface $product;

    #[ExposeInTemplate]
    public ?ProductVariantInterface $variant = null;

    #[ExposeInTemplate(name: 'channel_pricing')]
    public ?ChannelPricingInterface $channelPricing = null;

    #[ExposeInTemplate(name: 'comparison_group')]
    public ?string $comparisonGroup = null;

    public function __construct(private readonly ChannelContextInterface $channelContext)
    {
    }

    #[PostMount]
    public function postMount(): void
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();
        $taxon = $this->product->getMainTaxon();
        if ($taxon !== null) {
            while ($taxon->getParent() !== null && $taxon->getParent()?->getCode() !== 'products') {
                $taxon = $taxon->getParent();
            }
            $this->comparisonGroup = $taxon->getCode();
        }
        $variants = array_values(array_filter(
            $this->product->getVariants()->toArray(),
            static fn (mixed $variant): bool => $variant instanceof ProductVariantInterface,
        ));

        // A stable order avoids making the displayed SKU depend on collection/DB order.
        usort($variants, static fn (ProductVariantInterface $left, ProductVariantInterface $right): int => [(string) $left->getCode(), $left->getId() ?? 0] <=> [(string) $right->getCode(), $right->getId() ?? 0]);

        foreach ($variants as $variant) {
            $pricing = $variant->getChannelPricingForChannel($channel);
            if ($variant->isEnabled() && $pricing?->getPrice() !== null) {
                $this->variant = $variant;
                $this->channelPricing = $pricing;

                return;
            }
        }

        foreach ($variants as $variant) {
            if ($variant->isEnabled()) {
                $this->variant = $variant;

                return;
            }
        }

        // Keep malformed legacy products renderable even if no variant is enabled.
        $this->variant = $variants[0] ?? null;
    }
}
