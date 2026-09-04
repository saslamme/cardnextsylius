<?php

declare(strict_types=1);

namespace App\OrderProcessing;

use App\Entity\Order\Adjustment;
use App\Entity\Order\OrderItem;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductBundleChannel;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Rebuilds trusted bundle adjustments after product/B2B unit prices are resolved. */
#[AutoconfigureTag('sylius.order_processor', ['priority' => 40])]
final class BundleDiscountOrderProcessor implements OrderProcessorInterface
{
    public const ADJUSTMENT_TYPE = 'bundle_discount';

    public function process(OrderInterface $order): void
    {
        $order->removeAdjustments(self::ADJUSTMENT_TYPE);
        if (!$order instanceof CoreOrderInterface || !$order->canBeProcessed()) return;
        $channel = $order->getChannel();
        if ($channel === null) return;

        /** @var array<string, list<OrderItem>> $groups */
        $groups = [];
        foreach ($order->getItems() as $item) {
            if ($item instanceof OrderItem && $item->isBundleItem()) $groups[(string) $item->getBundleGroupKey()][] = $item;
        }

        foreach ($groups as $groupKey => $items) {
            $bundle = $this->sameBundle($items);
            if ($bundle === null || !$bundle->isEnabled()) continue;
            $configuration = $bundle->configurationFor((string) $channel->getCode());
            if (!$configuration instanceof ProductBundleChannel || !$configuration->isEnabled()) continue;
            $bundleQuantity = $this->completeBundleQuantity($bundle, $items);
            if ($bundleQuantity === null) continue;
            $subtotal = array_sum(array_map(static fn (OrderItem $item): int => $item->getUnitPrice() * $item->getQuantity(), $items));
            $discount = $configuration->calculateDiscount($subtotal, $bundleQuantity);
            if ($discount === 0) continue;

            $adjustment = new Adjustment();
            $adjustment->setType(self::ADJUSTMENT_TYPE);
            $adjustment->setLabel(sprintf('Bundle-Rabatt „%s“', $bundle->getName()));
            $adjustment->setAmount(-$discount);
            $adjustment->setDetails(['bundleCode' => $bundle->getCode(), 'bundleGroupKey' => $groupKey]);
            $order->addAdjustment($adjustment);
        }
    }

    /** @param list<OrderItem> $items */
    private function sameBundle(array $items): ?ProductBundle
    {
        $bundle = $items[0]->getBundle() ?? null;
        foreach ($items as $item) if ($item->getBundle() !== $bundle) return null;
        return $bundle;
    }

    /** @param list<OrderItem> $items */
    private function completeBundleQuantity(ProductBundle $bundle, array $items): ?int
    {
        $main = array_values(array_filter($items, static fn (OrderItem $item): bool => $item->getBundleRole() === OrderItem::BUNDLE_ROLE_MAIN));
        if (count($main) !== 1 || $main[0]->getVariant()?->getProduct() !== $bundle->getMainProduct()) return null;
        $quantity = $main[0]->getQuantity();
        if ($quantity < 1) return null;

        $expected = [];
        foreach ($bundle->getItems() as $definition) {
            if ($definition->isEnabled()) $expected[(string) $definition->getVariant()->getId()] = $definition->getQuantity() * $quantity;
        }
        $actual = [];
        foreach ($items as $item) {
            if ($item->getBundleRole() !== OrderItem::BUNDLE_ROLE_COMPONENT || $item->getVariant() === null) continue;
            $key = (string) $item->getVariant()->getId();
            $actual[$key] = ($actual[$key] ?? 0) + $item->getQuantity();
        }
        ksort($expected);
        ksort($actual);
        return $actual === $expected ? $quantity : null;
    }
}
