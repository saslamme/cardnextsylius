<?php

declare(strict_types=1);

namespace App\Service\Order;

use App\Entity\Channel\Channel;
use App\Entity\Customer\Customer;
use App\Entity\Order\ConfiguredOrderItem;
use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductVariant;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Service\Configurator\ConfiguredCartItemFactory;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final readonly class ReorderService
{
    /** @param CartItemFactoryInterface<OrderItem> $cartItemFactory */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartContextInterface $cartContext,
        private CartItemFactoryInterface $cartItemFactory,
        private OrderItemQuantityModifierInterface $quantityModifier,
        private AvailabilityCheckerInterface $availabilityChecker,
        private ConfiguredCartItemFactory $configuredItemFactory,
        private OrderProcessorInterface $orderProcessor,
    ) {
    }

    public function reorder(Order $source, Customer $customer, Channel $channel): ReorderResult
    {
        $cart = $this->cartContext->getCart();
        if (!$cart instanceof Order || ($cart->getCustomer() !== null && $cart->getCustomer() !== $customer) || $cart->getChannel() !== $channel) {
            throw new \LogicException('The active cart does not belong to the current customer and channel.');
        }
        if ($cart->getCustomer() === null) {
            $cart->setCustomer($customer);
        }

        $regular = [];
        $bundleGroups = [];
        $skipped = $skippedBundles = $protection = 0;
        foreach ($source->getItems() as $oldItem) {
            if (!$oldItem instanceof OrderItem) {
                continue;
            }
            if ($oldItem->isAddon()) {
                ++$protection;
                continue;
            }
            if ($oldItem->isBundleItem()) {
                $bundleGroups[(string) $oldItem->getBundleGroupKey()][] = $oldItem;
                continue;
            }
            if ($this->isAvailable($oldItem->getVariant(), $oldItem->getQuantity(), $channel)) {
                $regular[] = $oldItem;
            } else {
                ++$skipped;
            }
        }

        $validBundles = [];
        foreach ($bundleGroups as $group) {
            $bundle = $group[0]->getBundle();
            if (!$this->isBundleAvailable($bundle, $group, $channel)) {
                ++$skippedBundles;
                continue;
            }
            $validBundles[] = $group;
        }

        $configured = [];
        $skippedConfigured = 0;
        foreach ($source->getConfiguredItems() as $oldConfigured) {
            try {
                $configured[] = $this->configuredItemFactory->create($oldConfigured->getConfiguratorCode(), $oldConfigured->getCanonicalConfiguration());
            } catch (InvalidConfigurationException|\DomainException) {
                ++$skippedConfigured;
            }
        }

        $added = count($regular) + count($validBundles) + count($configured);
        if ($added === 0) {
            return new ReorderResult(0, $skipped, $skippedConfigured, $skippedBundles, $protection);
        }

        return $this->entityManager->wrapInTransaction(function () use ($cart, $regular, $validBundles, $configured, $added, $skipped, $skippedConfigured, $skippedBundles, $protection): ReorderResult {
            foreach ($regular as $oldItem) {
                $this->addRegularItem($cart, $oldItem);
            }
            foreach ($validBundles as $group) {
                $key = self::uuid();
                foreach ($group as $oldItem) {
                    $item = $this->newItem($this->variant($oldItem), $oldItem->getQuantity());
                    $item->setBundle($oldItem->getBundle());
                    $item->setBundleGroupKey($key);
                    $item->setBundleRole($oldItem->getBundleRole());
                    $cart->addItem($item);
                }
            }
            foreach ($configured as $item) {
                $cart->addConfiguredItem($item);
            }
            // This is the only pricing step: no monetary value is copied from the old order.
            $this->orderProcessor->process($cart);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();

            return new ReorderResult($added, $skipped, $skippedConfigured, $skippedBundles, $protection);
        });
    }

    private function addRegularItem(Order $cart, OrderItem $oldItem): void
    {
        foreach ($cart->getItems() as $existing) {
            if ($existing instanceof OrderItem && !$existing->isBundleItem() && !$existing->isAddon() && $existing->getVariant() === $oldItem->getVariant()) {
                $this->quantityModifier->modify($existing, $existing->getQuantity() + $oldItem->getQuantity());
                return;
            }
        }
        $cart->addItem($this->newItem($this->variant($oldItem), $oldItem->getQuantity()));
    }

    private function newItem(ProductVariant $variant, int $quantity): OrderItem
    {
        $item = $this->cartItemFactory->createNew();
        $item->setVariant($variant);
        $this->quantityModifier->modify($item, $quantity);

        return $item;
    }

    private function variant(OrderItem $item): ProductVariant
    {
        $variant = $item->getVariant();
        if (!$variant instanceof ProductVariant) {
            throw new \LogicException('A validated reorder item no longer has a product variant.');
        }

        return $variant;
    }

    private function isAvailable(mixed $variant, int $quantity, Channel $channel): bool
    {
        if (!$variant instanceof ProductVariant || !$variant->isEnabled() || !$variant->isValidOrderQuantity($quantity)) {
            return false;
        }
        $product = $variant->getProduct();

        return $product instanceof Product && $product->isEnabled() && !$product->isAddonOnly() && $product->getChannels()->contains($channel) && $this->availabilityChecker->isStockSufficient($variant, $quantity);
    }

    /** @param list<OrderItem> $items */
    private function isBundleAvailable(?ProductBundle $bundle, array $items, Channel $channel): bool
    {
        if (!$bundle instanceof ProductBundle || !$bundle->isEnabled()) {
            return false;
        }
        $configuration = $bundle->configurationFor((string) $channel->getCode());
        if ($configuration === null || !$configuration->isEnabled()) {
            return false;
        }
        foreach ($items as $item) {
            if (!$this->isAvailable($item->getVariant(), $item->getQuantity(), $channel)) {
                return false;
            }
        }

        return true;
    }

    private static function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20);
    }
}
