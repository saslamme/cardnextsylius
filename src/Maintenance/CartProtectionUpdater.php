<?php

declare(strict_types=1);

namespace App\Maintenance;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\ProductVariant;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final readonly class CartProtectionUpdater
{
    /** @param CartItemFactoryInterface<OrderItem> $cartItemFactory */
    public function __construct(
        private CartItemFactoryInterface $cartItemFactory,
        private AddToCartCommandFactoryInterface $commandFactory,
        private OrderItemQuantityModifierInterface $quantityModifier,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function update(Order $cart, OrderItem $parent, ?ProductVariant $maintenanceVariant, ?ProductVariant $warrantyVariant): void
    {
        $this->replace($cart, $parent, $maintenanceVariant, OrderItem::ADDON_TYPE_MAINTENANCE);
        $this->replace($cart, $parent, $warrantyVariant, OrderItem::ADDON_TYPE_WARRANTY);
    }

    public function updateMaintenance(Order $cart, OrderItem $parent, ?ProductVariant $variant): void
    {
        $this->replace($cart, $parent, $variant, OrderItem::ADDON_TYPE_MAINTENANCE);
    }

    public function updateWarranty(Order $cart, OrderItem $parent, ?ProductVariant $variant): void
    {
        $this->replace($cart, $parent, $variant, OrderItem::ADDON_TYPE_WARRANTY);
    }

    private function replace(Order $cart, OrderItem $parent, ?ProductVariant $variant, string $addonType): void
    {
        foreach ($cart->getItems()->toArray() as $item) {
            if ($item instanceof OrderItem && $item->getParentItem() === $parent && $item->getAddonType() === $addonType) {
                $cart->removeItem($item);
            }
        }
        if ($variant === null) {
            return;
        }

        $addon = $this->cartItemFactory->createNew();
        $addon->setVariant($variant);
        $addon->setParentItem($parent);
        $addon->setAddonType($addonType);
        $this->quantityModifier->modify($addon, $parent->getQuantity());
        $command = $this->commandFactory->createWithCartAndCartItem($cart, $addon);
        $this->eventDispatcher->dispatch(new GenericEvent($command), SyliusCartEvents::CART_ITEM_ADD);
    }
}
