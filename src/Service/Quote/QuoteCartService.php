<?php

declare(strict_types=1);

namespace App\Service\Quote;

use App\Entity\Product\ProductVariant;
use App\Service\B2BPriceResolver;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class QuoteCartService
{
    public const SESSION_KEY = 'cardnext.quote_cart';

    public const MAX_QUANTITY = 100000;

    public function __construct(
        private RequestStack $requests,
        private EntityManagerInterface $em,
        private B2BPriceResolver $priceResolver,
        private CustomerContextInterface $customerContext,
        private ConfiguredQuoteSnapshotFactory $configuredSnapshots,
    )
    {
    }

    /** @return array{channel:string,items:array<string,int>,configuredItems:array<string,array{snapshot:array<string,mixed>}>} */
    public function cart(ChannelInterface $channel): array
    {
        $s = $this->requests->getSession();
        $stored = $s->get(self::SESSION_KEY);
        $cart = ['channel' => (string) $channel->getCode(), 'items' => [], 'configuredItems' => []];
        if (is_array($stored) && isset($stored['channel'],$stored['items']) && is_string($stored['channel']) && is_array($stored['items'])) {
            $cart['channel'] = $stored['channel'];
            foreach ($stored['items'] as $code => $quantity) {
                if (is_string($code) && is_int($quantity)) {
                    $cart['items'][$code] = $quantity;
                }
            }
            if (isset($stored['configuredItems']) && is_array($stored['configuredItems'])) {
                foreach ($stored['configuredItems'] as $key => $row) {
                    if (is_string($key) && is_array($row) && isset($row['snapshot']) && is_array($row['snapshot'])) { $cart['configuredItems'][$key] = ['snapshot' => $row['snapshot']]; }
                }
            }
        }if ($cart['channel'] !== $channel->getCode()) {
            $cart = ['channel' => (string) $channel->getCode(), 'items' => [], 'configuredItems' => []];
            $s->set(self::SESSION_KEY, $cart);
        }

        return $cart;
    }

    public function add(string $code, int $quantity, ChannelInterface $channel): bool
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY || !$this->resolve($code, $channel)) {
            return false;
        }$cart = $this->cart($channel);
        $cart['items'][$code] = min(self::MAX_QUANTITY, ($cart['items'][$code] ?? 0) + $quantity);
        $this->requests->getSession()->set(self::SESSION_KEY, $cart);

        return true;
    }

    public function update(string $code, int $quantity, ChannelInterface $channel): bool
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY || !$this->resolve($code, $channel)) {
            return false;
        }$cart = $this->cart($channel);
        if (!isset($cart['items'][$code])) {
            return false;
        }$cart['items'][$code] = $quantity;
        $this->requests->getSession()->set(self::SESSION_KEY, $cart);

        return true;
    }

    public function remove(string $code, ChannelInterface $channel): void
    {
        $cart = $this->cart($channel);
        unset($cart['items'][$code]);
        $this->requests->getSession()->set(self::SESSION_KEY, $cart);
    }

    public function clear(): void
    {
        $this->requests->getSession()->remove(self::SESSION_KEY);
    }

    public function count(ChannelInterface $channel): int
    {
        $cart = $this->cart($channel);
        return count($cart['items']) + count($cart['configuredItems']);
    }

    public function addConfigured(\App\Entity\Order\ConfiguredOrderItem $item, ChannelInterface $channel): string
    {
        if ($item->getChannelCode() !== $channel->getCode()) { throw new \DomainException('Configured item channel does not match quote cart channel.'); }
        $cart = $this->cart($channel); $key = 'cfg:' . $item->getConfigurationHash();
        $cart['configuredItems'][$key] = ['snapshot' => $this->configuredSnapshots->createSnapshot($item)];
        $this->requests->getSession()->set(self::SESSION_KEY, $cart);
        return $key;
    }

    public function removeConfigured(string $key, ChannelInterface $channel): void
    {
        $cart = $this->cart($channel); unset($cart['configuredItems'][$key]); $this->requests->getSession()->set(self::SESSION_KEY, $cart);
    }

    /** @param array<string,mixed> $snapshot */
    public function replaceConfigured(string $oldKey, array $snapshot, ChannelInterface $channel): void
    {
        $cart = $this->cart($channel); if (!isset($cart['configuredItems'][$oldKey])) { throw new \DomainException('Configured quote cart item not found.'); }
        unset($cart['configuredItems'][$oldKey]); $cart['configuredItems']['cfg:' . (string) $snapshot['configurationHash']] = ['snapshot' => $snapshot];
        $this->requests->getSession()->set(self::SESSION_KEY, $cart);
    }

    /** @return list<array{variant:ProductVariant,quantity:int,unitPrice:int,lineTotal:int}> */
    public function resolvedItems(ChannelInterface $channel): array
    {
        $cart = $this->cart($channel);
        $out = [];
        $changed = false;
        foreach ($cart['items'] as $code => $quantity) {
            $variant = $this->resolve($code, $channel);
            if (!$variant) {
                unset($cart['items'][$code]);
                $changed = true;

                continue;
            }$price = $this->priceResolver->resolve($variant, $channel, $quantity, $this->customerContext->getCustomer());
            if ($price === null) {
                unset($cart['items'][$code]);
                $changed = true;

                continue;
            }$out[] = ['type' => \App\Enum\Quote\QuoteItemType::Product, 'key' => $code, 'variant' => $variant, 'configuredSnapshot' => null, 'name' => (string) $variant->getProduct()?->getName(), 'quantity' => $quantity, 'unitPrice' => $price, 'lineTotal' => $price * $quantity];
        }if ($changed) {
            $this->requests->getSession()->set(self::SESSION_KEY, $cart);
        }

        foreach ($cart['configuredItems'] as $key => $row) {
            $snapshot = $row['snapshot'];
            $out[] = ['type' => \App\Enum\Quote\QuoteItemType::Configured, 'key' => $key, 'variant' => null, 'configuredSnapshot' => $snapshot, 'name' => (string) ($snapshot['configuratorName'] ?? ''), 'quantity' => (int) ($snapshot['quantity'] ?? 0), 'unitPrice' => (int) ($snapshot['unitAmount'] ?? 0), 'lineTotal' => (int) ($snapshot['total'] ?? 0)];
        }
        return $out;
    }

    private function resolve(string $code, ChannelInterface $channel): ?ProductVariant
    {
        $variant = $this->em->getRepository(ProductVariant::class)->findOneBy(['code' => $code]);
        if (!$variant instanceof ProductVariant || !$variant->isEnabled()) {
            return null;
        }$product = $variant->getProduct();
        if (!$product instanceof \Sylius\Component\Core\Model\ProductInterface || !$product->isEnabled() || !$product->getChannels()->contains($channel)) {
            return null;
        }if ($variant->getChannelPricingForChannel($channel)?->getPrice() === null) {
            return null;
        }

        return $variant;
    }
}
