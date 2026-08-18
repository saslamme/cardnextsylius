<?php

declare(strict_types=1);

namespace App\OrderProcessing;

use App\Entity\Order\Adjustment;
use App\Entity\Order\Order;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('sylius.order_processor', ['priority' => 5])]
final class ConfiguredItemsOrderProcessor implements OrderProcessorInterface
{
    public const ADJUSTMENT_TYPE = 'cardnext_configured_items';

    public function process(OrderInterface $order): void
    {
        if (!$order instanceof Order) {
            return;
        }
        $order->removeAdjustments(self::ADJUSTMENT_TYPE);
        $total = array_sum(array_map(static fn ($item): int => $item->getTotal(), $order->getConfiguredItems()->toArray()));
        if ($total === 0) {
            return;
        }
        $adjustment = new Adjustment();
        $adjustment->setType(self::ADJUSTMENT_TYPE);
        $adjustment->setLabel('Konfigurierte Artikel');
        $adjustment->setAmount($total);
        $order->addAdjustment($adjustment);
    }
}
