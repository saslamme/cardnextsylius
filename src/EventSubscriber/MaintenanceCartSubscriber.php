<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final readonly class MaintenanceCartSubscriber implements EventSubscriberInterface
{
    public function __construct(private OrderItemQuantityModifierInterface $quantityModifier)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [SyliusCartEvents::CART_CHANGE => ['synchronize', 100], SyliusCartEvents::CART_ITEM_REMOVE => ['removeChildren', 100]];
    }

    public function synchronize(GenericEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof Order) {
            return;
        }
        foreach ($order->getItems() as $item) {
            if (!$item instanceof OrderItem || !$item->isMaintenanceAddon()) {
                continue;
            }
            $parent = $item->getParentItem();
            if ($parent === null || !$order->hasItem($parent)) {
                $order->removeItem($item);

                continue;
            }
            $this->quantityModifier->modify($item, $parent->getQuantity());
        }
    }

    public function removeChildren(GenericEvent $event): void
    {
        $parent = $event->getSubject();
        if (!$parent instanceof OrderItem || $parent->isMaintenanceAddon() || ($order = $parent->getOrder()) === null) {
            return;
        }
        foreach ($order->getItems() as $item) {
            if ($item instanceof OrderItem && $item->getParentItem() === $parent) {
                $order->removeItem($item);
            }
        }
    }
}
