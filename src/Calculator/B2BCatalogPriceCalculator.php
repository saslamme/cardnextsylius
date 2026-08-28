<?php

declare(strict_types=1);

namespace App\Calculator;

use App\Service\B2BPriceResolver;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator('sylius.calculator.product_variant_catalog_price', priority: 100)]
final readonly class B2BCatalogPriceCalculator implements ProductVariantPricesCalculatorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private ProductVariantPricesCalculatorInterface $inner,
        private B2BPriceResolver $priceResolver,
        private CustomerContextInterface $customerContext,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function calculate(ProductVariantInterface $productVariant, array $context): int
    {
        $channel = $context['channel'] ?? null;

        if ($channel instanceof ChannelInterface) {
            $resolvedPrice = $this->priceResolver->resolve(
                $productVariant,
                $channel,
                1,
                $this->customerContext->getCustomer(),
            );

            if ($resolvedPrice !== null) {
                return $resolvedPrice;
            }
        }

        return $this->inner->calculate($productVariant, $context);
    }

    /** @param array<string, mixed> $context */
    public function calculateOriginal(ProductVariantInterface $productVariant, array $context): int
    {
        return $this->inner->calculateOriginal($productVariant, $context);
    }

    /** @param array<string, mixed> $context */
    public function calculateLowestPriceBeforeDiscount(ProductVariantInterface $productVariant, array $context): ?int
    {
        return $this->inner->calculateLowestPriceBeforeDiscount($productVariant, $context);
    }
}
