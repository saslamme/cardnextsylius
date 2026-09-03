<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer\Customer;
use App\Entity\Pricing\VariantTierPrice;
use App\Entity\Product\CustomerVariantPriceRule;
use App\Entity\Product\VariantPriceRule;
use App\Pricing\ResolvedVariantPrice;
use App\Pricing\VariantTierPriceResolver;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Symfony\Contracts\Service\ResetInterface;

/** Central price-source pipeline. Source priority is deliberate, never amount-based. */
final class B2BPriceResolver implements ResetInterface
{
    /** @var array<string, array{groupRules:list<VariantPriceRule>,customerRules:list<CustomerVariantPriceRule>,publicTiers:list<VariantTierPrice>,groupCode:string}> */
    private array $ruleSets = [];

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly VariantTierPriceResolver $tierResolver) {}
    public function reset(): void { $this->ruleSets = []; }

    public function resolve(ProductVariantInterface $variant, ChannelInterface $channel, int $quantity, ?CustomerInterface $customer = null): ?int
    {
        return $this->resolvePrice($variant, $channel, $quantity, $customer)?->price;
    }

    public function resolvePrice(ProductVariantInterface $variant, ChannelInterface $channel, int $quantity, ?CustomerInterface $customer = null): ?ResolvedVariantPrice
    {
        $set = $this->getRuleSet($variant, $channel, $customer);
        $quantity = max(1, $quantity);
        $customerRule = $this->findBestApplicableRule($set['customerRules'], $quantity);
        if ($customerRule instanceof CustomerVariantPriceRule) return new ResolvedVariantPrice($customerRule->getPrice(), ResolvedVariantPrice::CUSTOMER);

        $groupRule = $this->findBestApplicableRule($set['groupRules'], $quantity);
        if ($groupRule instanceof VariantPriceRule) return new ResolvedVariantPrice($groupRule->getPrice(), ResolvedVariantPrice::CUSTOMER_GROUP);

        $tier = $this->findBestApplicableRule($set['publicTiers'], $quantity);
        if ($tier instanceof VariantTierPrice) return new ResolvedVariantPrice($tier->getPrice(), ResolvedVariantPrice::PUBLIC_TIER);

        $base = $variant->getChannelPricingForChannel($channel)?->getPrice();
        return $base === null ? null : new ResolvedVariantPrice($base, ResolvedVariantPrice::CHANNEL_PRICING);
    }

    public function resolveRule(ProductVariantInterface $variant, ChannelInterface $channel, int $quantity, ?CustomerInterface $customer = null): CustomerVariantPriceRule|VariantPriceRule|VariantTierPrice|null
    {
        $set = $this->getRuleSet($variant, $channel, $customer);
        $quantity = max(1, $quantity);
        return $this->findBestApplicableRule($set['customerRules'], $quantity)
            ?? $this->findBestApplicableRule($set['groupRules'], $quantity)
            ?? $this->findBestApplicableRule($set['publicTiers'], $quantity);
    }

    /** @return list<array{min_quantity:int,price:int,source:string,customer_group_code:string,customer_email:string}> */
    public function getEffectiveTiers(ProductVariantInterface $variant, ChannelInterface $channel, ?CustomerInterface $customer = null): array
    {
        $base = $variant->getChannelPricingForChannel($channel)?->getPrice();
        if ($base === null) return [];
        $set = $this->getRuleSet($variant, $channel, $customer);
        if ($set['customerRules'] === [] && $set['groupRules'] === [] && $set['publicTiers'] === []) return [];
        $points = [1];
        foreach (['customerRules', 'groupRules', 'publicTiers'] as $key) foreach ($set[$key] as $rule) $points[] = $rule->getMinQuantity();
        $points = array_values(array_unique($points)); sort($points, SORT_NUMERIC);
        $tiers = []; $previous = null;
        foreach ($points as $quantity) {
            $rule = $this->resolveRule($variant, $channel, $quantity, $customer);
            $result = $this->resolvePrice($variant, $channel, $quantity, $customer);
            if ($result === null) continue;
            $group = $rule instanceof VariantPriceRule ? $rule->getCustomerGroupCode() : '';
            $email = $rule instanceof CustomerVariantPriceRule ? (string) $rule->getCustomer()->getEmail() : '';
            $row = ['min_quantity' => $quantity, 'price' => $result->price, 'source' => $result->source, 'customer_group_code' => $group, 'customer_email' => $email];
            if ($previous !== null && $previous['price'] === $row['price'] && $previous['source'] === $row['source']) continue;
            $tiers[] = $row; $previous = $row;
        }
        return $tiers;
    }

    public function hasEffectiveRules(ProductVariantInterface $variant, ChannelInterface $channel, ?CustomerInterface $customer = null): bool
    {
        $set = $this->getRuleSet($variant, $channel, $customer);
        return $set['customerRules'] !== [] || $set['groupRules'] !== [] || $set['publicTiers'] !== [];
    }

    /** @return list<VariantPriceRule> */
    public function findRulesForVariant(ProductVariantInterface $variant): array
    {
        return $this->entityManager->getRepository(VariantPriceRule::class)->findBy(['variant' => $variant], ['channelCode' => 'ASC', 'customerGroupCode' => 'ASC', 'minQuantity' => 'ASC']);
    }

    /**
     * @template T of CustomerVariantPriceRule|VariantPriceRule|VariantTierPrice
     * @param iterable<T> $rules
     * @return T|null
     */
    private function findBestApplicableRule(iterable $rules, int $quantity): CustomerVariantPriceRule|VariantPriceRule|VariantTierPrice|null
    {
        $best = null;
        foreach ($rules as $rule) if ($rule->getMinQuantity() <= $quantity && ($best === null || $rule->getMinQuantity() > $best->getMinQuantity())) $best = $rule;
        return $best;
    }

    /** @return array{groupRules:list<VariantPriceRule>,customerRules:list<CustomerVariantPriceRule>,publicTiers:list<VariantTierPrice>,groupCode:string} */
    private function getRuleSet(ProductVariantInterface $variant, ChannelInterface $channel, ?CustomerInterface $customer): array
    {
        $b2b = $customer instanceof Customer && $customer->isB2bCustomer() ? $customer : null;
        $groupCode = $b2b !== null ? trim((string) $b2b->getGroup()?->getCode()) : '';
        $key = implode(':', [spl_object_id($variant), spl_object_id($channel), $customer ? spl_object_id($customer) : 'guest', $groupCode]);
        if (isset($this->ruleSets[$key])) return $this->ruleSets[$key];
        $groupRules = $groupCode === '' ? [] : $this->queryRules($variant, $channel, $groupCode);
        $customerRules = $b2b === null ? [] : $this->queryCustomerRules($variant, $channel, $b2b);
        return $this->ruleSets[$key] = ['groupRules' => $groupRules, 'customerRules' => $customerRules, 'publicTiers' => $this->tierResolver->tiers($variant, $channel), 'groupCode' => $groupCode];
    }

    /** @return list<VariantPriceRule> */
    private function queryRules(ProductVariantInterface $variant, ChannelInterface $channel, string $group): array
    {
        $rules = $this->entityManager->createQueryBuilder()->select('rule')->from(VariantPriceRule::class, 'rule')->andWhere('rule.variant = :variant')->andWhere('rule.channelCode = :channel')->andWhere('rule.customerGroupCode = :group')->andWhere('rule.enabled = true')->setParameter('variant', $variant)->setParameter('channel', (string) $channel->getCode())->setParameter('group', $group)->orderBy('rule.minQuantity', 'ASC')->getQuery()->getResult();
        return is_array($rules) ? array_values(array_filter($rules, static fn (mixed $rule): bool => $rule instanceof VariantPriceRule)) : [];
    }

    /** @return list<CustomerVariantPriceRule> */
    private function queryCustomerRules(ProductVariantInterface $variant, ChannelInterface $channel, Customer $customer): array
    {
        $rules = $this->entityManager->createQueryBuilder()->select('rule')->from(CustomerVariantPriceRule::class, 'rule')->andWhere('rule.variant = :variant')->andWhere('rule.channelCode = :channel')->andWhere('rule.customer = :customer')->andWhere('rule.enabled = true')->setParameter('variant', $variant)->setParameter('channel', (string) $channel->getCode())->setParameter('customer', $customer)->orderBy('rule.minQuantity', 'ASC')->getQuery()->getResult();
        return is_array($rules) ? array_values(array_filter($rules, static fn (mixed $rule): bool => $rule instanceof CustomerVariantPriceRule)) : [];
    }
}
