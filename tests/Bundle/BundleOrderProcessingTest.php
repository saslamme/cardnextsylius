<?php

declare(strict_types=1);

namespace App\Tests\Bundle;

use App\Entity\Channel\Channel;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Order\OrderItemUnit;
use App\Entity\Product\Product;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductBundleChannel;
use App\Entity\Product\ProductBundleItem;
use App\Entity\Product\ProductVariant;
use App\OrderProcessing\BundleDiscountOrderProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\CompositeOrderProcessor;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class BundleOrderProcessingTest extends TestCase
{
    public function testCompositePricesCompleteBundleBeforeApplyingPercentageDiscount(): void
    {
        [$order, $bundle, $variants] = $this->bundleOrder(ProductBundleChannel::DISCOUNT_PERCENT, 1000);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-a', 1);

        $this->processWithPrices($order, [92000, 5240, 1050]);

        self::assertSame([92000, 5240, 1050], $this->unitPrices($order));
        self::assertSame(98290, $order->getItemsTotal());
        self::assertSame(-9829, $order->getAdjustmentsTotal(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(88461, $order->getTotal());
        self::assertNotContains(0, $this->unitPrices($order));
    }

    public function testNoDiscountKeepsTheSumOfServerResolvedPrices(): void
    {
        [$order, $bundle, $variants] = $this->bundleOrder(ProductBundleChannel::DISCOUNT_NONE);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-a', 1);

        $this->processWithPrices($order, [92000, 5240, 1050]);

        self::assertCount(0, $order->getAdjustments(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(98290, $order->getTotal());
    }

    public function testIncompleteBundleHasPricesButNoDiscount(): void
    {
        [$order, $bundle, $variants] = $this->bundleOrder(ProductBundleChannel::DISCOUNT_PERCENT, 1000);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-a', 1, false);

        $this->processWithPrices($order, [92000, 5240]);

        self::assertSame([92000, 5240], $this->unitPrices($order));
        self::assertSame(97240, $order->getTotal());
        self::assertCount(0, $order->getAdjustments(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
    }

    public function testFixedDiscountAndBundleQuantityAreCalculatedPerBundle(): void
    {
        [$order, $bundle, $variants] = $this->bundleOrder(ProductBundleChannel::DISCOUNT_FIXED, 5000);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-a', 2);

        $this->processWithPrices($order, [92000, 5240, 1050]);

        self::assertSame([2, 2, 2], array_map(static fn ($item): int => $item->getQuantity(), $order->getItems()->toArray()));
        self::assertSame(-10000, $order->getAdjustmentsTotal(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(186580, $order->getTotal());
    }

    public function testRegularItemWithSameVariantIsNotIncludedInBundleDiscount(): void
    {
        [$order, $bundle, $variants] = $this->bundleOrder(ProductBundleChannel::DISCOUNT_PERCENT, 1000);
        $this->addPricedItem($order, $variants[1], 1, null, null, null);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-a', 1);

        $this->processWithPrices($order, [5240, 92000, 5240, 1050]);

        self::assertCount(4, $order->getItems());
        $regularItem = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $regularItem);
        self::assertFalse($regularItem->isBundleItem());
        self::assertSame(-9829, $order->getAdjustmentsTotal(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(93701, $order->getTotal());
    }

    public function testTwoGroupsReceiveIndependentDiscounts(): void
    {
        [$order, $bundle, $variants] = $this->bundleOrder(ProductBundleChannel::DISCOUNT_PERCENT, 1000);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-a', 1);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-b', 1);

        $this->processWithPrices($order, [92000, 5240, 1050, 92000, 5240, 1050]);

        self::assertCount(2, $order->getAdjustments(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(-19658, $order->getAdjustmentsTotal(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(176922, $order->getTotal());
    }

    public function testRemovingAComponentRemovesStaleDiscountWithoutChangingPrices(): void
    {
        [$order, $bundle, $variants] = $this->bundleOrder(ProductBundleChannel::DISCOUNT_PERCENT, 1000);
        $this->addBundleGroup($order, $bundle, $variants, 'bundle-a', 1);
        $this->processWithPrices($order, [92000, 5240, 1050]);
        $removedItem = $order->getItems()->last();
        self::assertInstanceOf(OrderItem::class, $removedItem);
        $order->removeItem($removedItem);

        (new BundleDiscountOrderProcessor())->process($order);

        self::assertSame([92000, 5240], $this->unitPrices($order));
        self::assertCount(0, $order->getAdjustments(BundleDiscountOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(97240, $order->getTotal());
    }

    /** @return array{Order, ProductBundle, list<ProductVariant>} */
    private function bundleOrder(string $discountType, ?int $discount = null): array
    {
        $channel = new Channel();
        $channel->setCode('WEB');
        $mainProduct = new Product();
        $componentProductA = new Product();
        $componentProductB = new Product();
        $variants = [new ProductVariant(), new ProductVariant(), new ProductVariant()];
        $variants[0]->setProduct($mainProduct);
        $variants[1]->setProduct($componentProductA);
        $variants[2]->setProduct($componentProductB);
        foreach ($variants as $index => $variant) {
            (new \ReflectionProperty($variant, 'id'))->setValue($variant, $index + 1);
        }

        $bundle = new ProductBundle();
        $bundle->setCode('starter');
        $bundle->setName('Starterset');
        $bundle->setMainProduct($mainProduct);
        foreach ([[1, 1], [2, 1]] as [$variantIndex, $quantity]) {
            $definition = new ProductBundleItem();
            $definition->setVariant($variants[$variantIndex]);
            $definition->setQuantity($quantity);
            $bundle->addItem($definition);
        }
        $configuration = new ProductBundleChannel();
        $configuration->setChannel($channel);
        $configuration->setDiscountType($discountType);
        if ($discountType === ProductBundleChannel::DISCOUNT_PERCENT) $configuration->setPercentageDiscount($discount);
        if ($discountType === ProductBundleChannel::DISCOUNT_FIXED) $configuration->setFixedDiscount($discount);
        $bundle->addChannelConfiguration($configuration);

        $order = new Order();
        $order->setChannel($channel);

        return [$order, $bundle, $variants];
    }

    /** @param list<ProductVariant> $variants */
    private function addBundleGroup(Order $order, ProductBundle $bundle, array $variants, string $key, int $quantity, bool $complete = true): void
    {
        $this->addPricedItem($order, $variants[0], $quantity, $bundle, $key, OrderItem::BUNDLE_ROLE_MAIN);
        $this->addPricedItem($order, $variants[1], $quantity, $bundle, $key, OrderItem::BUNDLE_ROLE_COMPONENT);
        if ($complete) $this->addPricedItem($order, $variants[2], $quantity, $bundle, $key, OrderItem::BUNDLE_ROLE_COMPONENT);
    }

    private function addPricedItem(Order $order, ProductVariant $variant, int $quantity, ?ProductBundle $bundle, ?string $key, ?string $role): void
    {
        $item = new OrderItem();
        $item->setVariant($variant);
        $item->setBundle($bundle);
        $item->setBundleGroupKey($key);
        $item->setBundleRole($role);
        for ($i = 0; $i < $quantity; ++$i) new OrderItemUnit($item);
        $order->addItem($item);
    }

    /** @param list<int> $prices */
    private function processWithPrices(Order $order, array $prices): void
    {
        $pricing = new class($prices) implements OrderProcessorInterface {
            /** @param list<int> $prices */
            public function __construct(private readonly array $prices) {}
            public function process(OrderInterface $order): void
            {
                foreach ($order->getItems() as $index => $item) $item->setUnitPrice($this->prices[$index]);
            }
        };
        $processor = new CompositeOrderProcessor();
        $processor->addProcessor(new BundleDiscountOrderProcessor(), 40);
        $processor->addProcessor($pricing, 49);
        $processor->process($order);
    }

    /** @return list<int> */
    private function unitPrices(Order $order): array
    {
        return array_values(array_map(static fn ($item): int => $item->getUnitPrice(), $order->getItems()->toArray()));
    }
}
