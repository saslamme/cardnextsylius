<?php

declare(strict_types=1);

namespace App\Tests\Order;

use App\Entity\Channel\Channel;
use App\Entity\Customer\Customer;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Service\Configurator\ConfiguredCartItemFactory;
use App\Service\Order\ReorderService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class ReorderServiceTest extends TestCase
{
    /** @dataProvider regularQuantityCases */
    public function testRegularItemsArePlannedAgainstTheResultingCartQuantity(
        int $existing,
        array $reorderQuantities,
        int $stock,
        int $minimum,
        int $increment,
        int $expectedQuantity,
        int $expectedAdded,
        int $expectedSkipped,
    ): void {
        $channel = new Channel();
        $channel->setCode('WEB');
        $customer = new Customer();
        $variant = $this->variant($channel, $minimum, $increment);
        $cart = $this->order($customer, $channel);
        $current = $this->item($variant, $existing);
        $cart->addItem($current);
        $source = new Order();
        foreach ($reorderQuantities as $quantity) {
            $source->addItem($this->item($variant, $quantity));
        }

        $result = $this->service($cart, $stock)->reorder($source, $customer, $channel);

        self::assertSame($expectedQuantity, $current->getQuantity());
        self::assertSame($expectedAdded, $result->addedItems);
        self::assertSame($expectedSkipped, $result->skippedItems);
    }

    /** @return iterable<string, array{int, list<int>, int, int, int, int, int, int}> */
    public static function regularQuantityCases(): iterable
    {
        yield 'combined stock is insufficient' => [8, [5], 10, 1, 1, 8, 0, 1];
        yield 'combined increment is invalid' => [5, [5], 100, 12, 6, 5, 0, 1];
        yield 'historical lines use the cumulative plan' => [2, [4, 4], 8, 2, 2, 6, 1, 1];
        yield 'valid combined quantity is merged' => [5, [5], 10, 5, 5, 10, 1, 0];
    }

    private function service(Order $cart, int $stock): ReorderService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $callback): mixed => $callback());
        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext->method('getCart')->willReturn($cart);
        $factory = $this->createMock(CartItemFactoryInterface::class);
        $factory->method('createNew')->willReturnCallback(static fn (): OrderItem => new OrderItem());
        $modifier = $this->createMock(OrderItemQuantityModifierInterface::class);
        $modifier->method('modify')->willReturnCallback(static function (OrderItem $item, int $quantity): void {
            self::setQuantity($item, $quantity);
        });
        $availability = $this->createMock(AvailabilityCheckerInterface::class);
        $availability->method('isStockSufficient')->willReturnCallback(static fn (ProductVariant $variant, int $quantity): bool => $quantity <= $stock);
        $configuredFactory = (new \ReflectionClass(ConfiguredCartItemFactory::class))->newInstanceWithoutConstructor();

        return new ReorderService(
            $entityManager,
            $cartContext,
            $factory,
            $modifier,
            $availability,
            $configuredFactory,
            $this->createMock(OrderProcessorInterface::class),
        );
    }

    private function variant(Channel $channel, int $minimum, int $increment): ProductVariant
    {
        $product = new Product();
        $product->setEnabled(true);
        $product->addChannel($channel);
        $variant = new ProductVariant();
        $variant->setEnabled(true);
        $variant->setMinimumOrderQuantity($minimum);
        $variant->setOrderIncrement($increment);
        $product->addVariant($variant);

        return $variant;
    }

    private function order(Customer $customer, Channel $channel): Order
    {
        $order = new Order();
        $order->setCustomer($customer);
        $order->setChannel($channel);

        return $order;
    }

    private function item(ProductVariant $variant, int $quantity): OrderItem
    {
        $item = new OrderItem();
        $item->setVariant($variant);
        self::setQuantity($item, $quantity);

        return $item;
    }

    private static function setQuantity(OrderItem $item, int $quantity): void
    {
        $property = new \ReflectionProperty(\Sylius\Component\Order\Model\OrderItem::class, 'quantity');
        $property->setValue($item, $quantity);
    }
}
