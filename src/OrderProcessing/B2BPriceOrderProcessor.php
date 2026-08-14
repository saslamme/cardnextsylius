<?php

declare(strict_types=1);

namespace App\OrderProcessing;

use App\Service\B2BPriceResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Webmozart\Assert\Assert;

#[AutoconfigureTag('sylius.order_processor', ['priority' => 49])]
final readonly class B2BPriceOrderProcessor implements OrderProcessorInterface
{
    public function __construct(
        private B2BPriceResolver $priceResolver,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->canBeProcessed()) {
            return;
        }

        $channel = $order->getChannel();
        if ($channel === null) {
            return;
        }

        $customer = $order->getCustomer();

        foreach ($order->getItems() as $item) {
            if ($item->isImmutable() || $item->getVariant() === null) {
                continue;
            }

            $resolvedPrice = $this->priceResolver->resolve(
                $item->getVariant(),
                $channel,
                max(1, $item->getQuantity()),
                $customer,
            );

            if ($resolvedPrice !== null) {
                $item->setUnitPrice($resolvedPrice);
            }
        }
    }
}
