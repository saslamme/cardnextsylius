<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Order\Adjustment;
use App\Entity\Order\ConfiguredOrderItem;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Order\OrderItemUnit;
use App\OrderProcessing\ConfiguredItemsOrderProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Order\Model\Order as GenericSyliusOrder;

final class ConfiguredItemsOrderProcessorTest extends TestCase
{
    private ConfiguredItemsOrderProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ConfiguredItemsOrderProcessor();
    }

    public function testItAddsConfiguredTotalToAnEmptyOrder(): void
    {
        $order = new Order();
        $order->addConfiguredItem($this->configuredItem(25250));

        $this->processor->process($order);

        $this->assertConfiguredAdjustment($order, 25250);
        self::assertSame(25250, $order->getTotal());
    }

    public function testItReplacesTheAdjustmentAfterRepeatedPricingUpdates(): void
    {
        $order = new Order();
        $item = $this->configuredItem(25250);
        $order->addConfiguredItem($item);
        $this->processor->process($order);

        $item->replacePricing($this->configuredItem(67000));
        $this->processor->process($order);

        $this->assertConfiguredAdjustment($order, 67000);
        self::assertSame(67000, $order->getTotal());

        $item->replacePricing($this->configuredItem(81000));
        $this->processor->process($order);

        $this->assertConfiguredAdjustment($order, 81000);
        self::assertSame(81000, $order->getTotal());
    }

    public function testItRemovesTheAdjustmentWhenTheLastConfiguredItemIsRemoved(): void
    {
        $order = new Order();
        $item = $this->configuredItem(67000);
        $order->addConfiguredItem($item);
        $this->processor->process($order);

        $order->removeConfiguredItem($item);
        $this->processor->process($order);

        self::assertCount(0, $order->getAdjustments(ConfiguredItemsOrderProcessor::ADJUSTMENT_TYPE));
        self::assertSame(0, $order->getTotal());
    }

    public function testItSumsMultipleConfiguredItems(): void
    {
        $order = new Order();
        $order->addConfiguredItem($this->configuredItem(30000));
        $order->addConfiguredItem($this->configuredItem(40000));

        $this->processor->process($order);

        $this->assertConfiguredAdjustment($order, 70000);
        self::assertSame(70000, $order->getTotal());
    }

    public function testItPreservesStandardItemsAndOtherAdjustments(): void
    {
        $order = new Order();
        $standardItem = new OrderItem();
        $standardItem->setUnitPrice(12000);
        $standardItem->addUnit(new OrderItemUnit($standardItem));
        $standardItem->addUnit(new OrderItemUnit($standardItem));
        $order->addItem($standardItem);
        $order->addConfiguredItem($this->configuredItem(30000));

        $otherAdjustment = new Adjustment();
        $otherAdjustment->setType('other_adjustment');
        $otherAdjustment->setAmount(500);
        $order->addAdjustment($otherAdjustment);

        $this->processor->process($order);

        $this->assertConfiguredAdjustment($order, 30000);
        self::assertSame(500, $order->getAdjustments('other_adjustment')->first()->getAmount());
        self::assertSame(54500, $order->getTotal());
    }

    public function testRepeatedProcessingDoesNotDuplicateTheAdjustment(): void
    {
        $order = new Order();
        $order->addConfiguredItem($this->configuredItem(67000));

        $this->processor->process($order);
        $this->processor->process($order);
        $this->processor->process($order);

        $this->assertConfiguredAdjustment($order, 67000);
        self::assertSame(67000, $order->getTotal());
    }

    public function testItLeavesAnOrderWithoutConfiguredItemsContractUntouched(): void
    {
        $order = new GenericSyliusOrder();

        $this->processor->process($order);

        self::assertSame(0, $order->getTotal());
        self::assertCount(0, $order->getAdjustments());
    }

    public function testProcessorContainsNoPaymentProviderIntegration(): void
    {
        $source = strtolower((string) file_get_contents(__DIR__ . '/../../src/OrderProcessing/ConfiguredItemsOrderProcessor.php'));

        foreach (['mollie', 'paypal', 'stripe', 'adyen', 'payum'] as $provider) {
            self::assertStringNotContainsString($provider, $source);
        }
    }

    private function assertConfiguredAdjustment(Order $order, int $amount): void
    {
        $adjustments = $order->getAdjustments(ConfiguredItemsOrderProcessor::ADJUSTMENT_TYPE);

        self::assertCount(1, $adjustments);
        self::assertSame($amount, $adjustments->first()->getAmount());
    }

    private function configuredItem(int $total): ConfiguredOrderItem
    {
        return new ConfiguredOrderItem(
            'cards',
            'Cards',
            'de_DE',
            'WEB',
            'EUR',
            1,
            str_repeat('a', 64),
            [],
            [],
            [],
            $total,
            0,
            $total,
            $total,
            0,
            0,
            $total,
        );
    }
}
