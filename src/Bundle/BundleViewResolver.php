<?php

declare(strict_types=1);

namespace App\Bundle;

use App\Entity\Product\Product;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductVariant;
use App\Service\B2BPriceResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Core\Model\ChannelInterface;

final readonly class BundleViewResolver
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private CustomerContextInterface $customerContext,
        private AvailabilityCheckerInterface $availabilityChecker,
        private B2BPriceResolver $priceResolver,
    ) {}

    /** @return list<array{bundle:ProductBundle,configuration:object,main:array{variant:ProductVariant,quantity:int,unitPrice:int},components:list<array{variant:ProductVariant,quantity:int,unitPrice:int}>,completeTotal:int,discount:int,bundleTotal:int}> */
    public function forProduct(Product $product): array
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface) return [];
        $customer = $this->customerContext->getCustomer();
        $result = [];
        $mainVariant = null;
        foreach ($product->getEnabledVariants() as $candidate) {
            if ($candidate instanceof ProductVariant && $this->availabilityChecker->isStockAvailable($candidate)) { $mainVariant = $candidate; break; }
        }
        if (!$mainVariant instanceof ProductVariant) return [];
        foreach ($product->getBundles() as $bundle) {
            $configuration = $bundle->configurationFor((string) $channel->getCode());
            if (!$bundle->isEnabled() || $configuration === null || !$configuration->isEnabled()) continue;
            $mainPrice = $this->priceResolver->resolve($mainVariant, $channel, 1, $customer);
            if ($mainPrice === null) continue;
            $components = [];
            $total = $mainPrice;
            foreach ($bundle->getItems() as $item) {
                $variant = $item->getVariant();
                if (!$item->isEnabled() || !$variant->isEnabled() || !$this->availabilityChecker->isStockAvailable($variant)) { $components = []; break; }
                $price = $this->priceResolver->resolve($variant, $channel, $item->getQuantity(), $customer);
                $componentProduct = $variant->getProduct();
                if ($price === null || !$componentProduct instanceof Product || !$componentProduct->getChannels()->contains($channel)) { $components = []; break; }
                $components[] = ['variant' => $variant, 'quantity' => $item->getQuantity(), 'unitPrice' => $price];
                $total += $price * $item->getQuantity();
            }
            if ($components === []) continue;
            $result[] = ['bundle' => $bundle, 'configuration' => $configuration, 'main' => ['variant' => $mainVariant, 'quantity' => 1, 'unitPrice' => $mainPrice], 'components' => $components, 'completeTotal' => $total, 'discount' => $configuration->calculateDiscount($total), 'bundleTotal' => $total - $configuration->calculateDiscount($total)];
        }
        return $result;
    }
}
