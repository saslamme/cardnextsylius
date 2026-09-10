<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\ProductVariant;
use App\Maintenance\CartProtectionUpdater;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CartProtectionUpdaterTest extends TestCase
{
    public function testReplacingWarrantyPreservesMaintenanceAndParentQuantity(): void
    {
        $cart = new Order();
        $parent = new OrderItem();
        (new \ReflectionProperty($parent, 'quantity'))->setValue($parent, 2);
        $maintenance = new OrderItem();
        $maintenance->setParentItem($parent);
        $maintenance->setAddonType(OrderItem::ADDON_TYPE_MAINTENANCE);
        $oldWarranty = new OrderItem();
        $oldWarranty->setParentItem($parent);
        $oldWarranty->setAddonType(OrderItem::ADDON_TYPE_WARRANTY);
        $cart->addItem($parent);
        $cart->addItem($maintenance);
        $cart->addItem($oldWarranty);

        $replacement = new OrderItem();
        $factory = $this->createMock(CartItemFactoryInterface::class);
        $factory->method('createNew')->willReturn($replacement);
        $commandFactory = $this->createMock(AddToCartCommandFactoryInterface::class);
        $commandFactory->method('createWithCartAndCartItem')->willReturn($this->createMock(AddToCartCommandInterface::class));
        $quantityModifier = $this->createMock(OrderItemQuantityModifierInterface::class);
        $quantityModifier->expects(self::once())->method('modify')->with($replacement, 2);
        $updater = new CartProtectionUpdater($factory, $commandFactory, $quantityModifier, $this->createMock(EventDispatcherInterface::class));
        $variant = new ProductVariant();

        $updater->updateWarranty($cart, $parent, $variant);

        self::assertTrue($cart->hasItem($maintenance));
        self::assertFalse($cart->hasItem($oldWarranty));
        self::assertSame($parent, $replacement->getParentItem());
        self::assertSame(OrderItem::ADDON_TYPE_WARRANTY, $replacement->getAddonType());
        self::assertSame($variant, $replacement->getVariant());
        self::assertSame(2, $parent->getQuantity());
    }

    public function testNullOnlyRemovesRequestedGroup(): void
    {
        $cart = new Order();
        $parent = new OrderItem();
        $maintenance = new OrderItem();
        $maintenance->setParentItem($parent);
        $maintenance->setAddonType(OrderItem::ADDON_TYPE_MAINTENANCE);
        $warranty = new OrderItem();
        $warranty->setParentItem($parent);
        $warranty->setAddonType(OrderItem::ADDON_TYPE_WARRANTY);
        $cart->addItem($parent);
        $cart->addItem($maintenance);
        $cart->addItem($warranty);
        $updater = new CartProtectionUpdater(
            $this->createMock(CartItemFactoryInterface::class),
            $this->createMock(AddToCartCommandFactoryInterface::class),
            $this->createMock(OrderItemQuantityModifierInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $updater->updateMaintenance($cart, $parent, null);

        self::assertFalse($cart->hasItem($maintenance));
        self::assertTrue($cart->hasItem($warranty));
        self::assertTrue($cart->hasItem($parent));
    }
}
