<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer\Customer;
use App\Entity\Product\CustomerVariantPriceRule;
use App\Entity\Product\VariantPriceRule;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class B2BPriceResolver implements ResetInterface
{
    /**
     * @var array<string, array{
     *     regularRules:list<VariantPriceRule>,
     *     customerRules:list<CustomerVariantPriceRule>,
     *     groupCode:string
     * }>
     */
    private array $ruleSets = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function reset(): void
    {
        $this->ruleSets = [];
    }

    public function resolve(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        int $quantity,
        ?CustomerInterface $customer = null,
    ): ?int {
        $rule = $this->resolveRule($variant, $channel, max(1, $quantity), $customer);

        return $rule?->getPrice();
    }

    public function resolveRule(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        int $quantity,
        ?CustomerInterface $customer = null,
    ): CustomerVariantPriceRule|VariantPriceRule|null {
        $quantity = max(1, $quantity);

        $ruleSet = $this->getRuleSet($variant, $channel, $customer);

        return $this->resolveFromRuleSet($ruleSet, $quantity);
    }

    /**
     * Returns the effective price ladder for the current customer.
     *
     * Priority:
     * 1. individual customer price
     * 2. customer group price
     * 3. public quantity price
     * 4. normal Sylius channel price
     *
     * @return list<array{
     *   min_quantity:int,
     *   price:int,
     *   source:string,
     *   customer_group_code:string,
     *   customer_email:string
     * }>
     */
    public function getEffectiveTiers(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        ?CustomerInterface $customer = null,
    ): array {
        $basePrice = $variant->getChannelPricingForChannel($channel)?->getPrice();
        if ($basePrice === null) {
            return [];
        }

        $ruleSet = $this->getRuleSet($variant, $channel, $customer);
        $regularRules = $ruleSet['regularRules'];
        $customerRules = $ruleSet['customerRules'];

        if ($regularRules === [] && $customerRules === []) {
            return [];
        }

        $breakpoints = [1];

        foreach ($regularRules as $rule) {
            $breakpoints[] = $rule->getMinQuantity();
        }

        foreach ($customerRules as $rule) {
            $breakpoints[] = $rule->getMinQuantity();
        }

        $breakpoints = array_values(array_unique($breakpoints));
        sort($breakpoints, \SORT_NUMERIC);

        $tiers = [];
        $lastPrice = null;
        $lastSource = null;

        foreach ($breakpoints as $quantity) {
            $resolvedRule = $this->resolveFromRuleSet($ruleSet, $quantity);
            $price = $resolvedRule?->getPrice() ?? $basePrice;

            if ($resolvedRule instanceof CustomerVariantPriceRule) {
                $source = 'customer';
                $customerGroupCode = '';
                $customerEmail = (string) $resolvedRule->getCustomer()->getEmail();
            } elseif ($resolvedRule instanceof VariantPriceRule) {
                $source = $resolvedRule->getCustomerGroupCode() !== '' ? 'group' : 'public';
                $customerGroupCode = $resolvedRule->getCustomerGroupCode();
                $customerEmail = '';
            } else {
                $source = 'base';
                $customerGroupCode = '';
                $customerEmail = '';
            }

            // If neither price nor source changes, the breakpoint is irrelevant.
            if ($lastPrice !== null && $lastPrice === $price && $lastSource === $source) {
                continue;
            }

            $tiers[] = [
                'min_quantity' => $quantity,
                'price' => $price,
                'source' => $source,
                'customer_group_code' => $customerGroupCode,
                'customer_email' => $customerEmail,
            ];

            $lastPrice = $price;
            $lastSource = $source;
        }

        return $tiers;
    }

    public function hasEffectiveRules(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        ?CustomerInterface $customer = null,
    ): bool {
        $ruleSet = $this->getRuleSet($variant, $channel, $customer);

        return $ruleSet['customerRules'] !== [] || $ruleSet['regularRules'] !== [];
    }

    /**
     * @return list<VariantPriceRule>
     */
    public function findRulesForVariant(ProductVariantInterface $variant): array
    {
        /** @var list<VariantPriceRule> $rules */
        $rules = $this->entityManager->getRepository(VariantPriceRule::class)->findBy(
            ['variant' => $variant],
            [
                'channelCode' => 'ASC',
                'customerGroupCode' => 'ASC',
                'minQuantity' => 'ASC',
            ],
        );

        return $rules;
    }

    private function getActiveB2bCustomer(?CustomerInterface $customer): ?Customer
    {
        return $customer instanceof Customer && $customer->isB2bCustomer() ? $customer : null;
    }

    /**
     * @param array{regularRules:list<VariantPriceRule>, customerRules:list<CustomerVariantPriceRule>, groupCode:string} $ruleSet
     */
    private function resolveFromRuleSet(array $ruleSet, int $quantity): CustomerVariantPriceRule|VariantPriceRule|null
    {
        $quantity = max(1, $quantity);

        $customerRule = $this->findBestApplicableRule($ruleSet['customerRules'], $quantity);
        if ($customerRule instanceof CustomerVariantPriceRule) {
            return $customerRule;
        }

        if ($ruleSet['groupCode'] !== '') {
            $groupRules = array_filter(
                $ruleSet['regularRules'],
                static fn (VariantPriceRule $rule): bool => $rule->getCustomerGroupCode() === $ruleSet['groupCode'],
            );
            $groupRule = $this->findBestApplicableRule($groupRules, $quantity);
            if ($groupRule instanceof VariantPriceRule) {
                return $groupRule;
            }
        }

        $publicRules = array_filter(
            $ruleSet['regularRules'],
            static fn (VariantPriceRule $rule): bool => $rule->getCustomerGroupCode() === '',
        );

        return $this->findBestApplicableRule($publicRules, $quantity);
    }

    /**
     * @template T of CustomerVariantPriceRule|VariantPriceRule
     *
     * @param iterable<T> $rules
     *
     * @return T|null
     */
    private function findBestApplicableRule(iterable $rules, int $quantity): CustomerVariantPriceRule|VariantPriceRule|null
    {
        $bestRule = null;

        foreach ($rules as $rule) {
            if ($rule->getMinQuantity() <= $quantity &&
                ($bestRule === null || $rule->getMinQuantity() > $bestRule->getMinQuantity())) {
                $bestRule = $rule;
            }
        }

        return $bestRule;
    }

    /**
     * @return array{regularRules:list<VariantPriceRule>, customerRules:list<CustomerVariantPriceRule>, groupCode:string}
     */
    private function getRuleSet(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        ?CustomerInterface $customer,
    ): array {
        $b2bCustomer = $this->getActiveB2bCustomer($customer);
        $groupCode = $b2bCustomer !== null ? trim((string) $b2bCustomer->getGroup()?->getCode()) : '';
        $customerGroupCode = trim((string) $customer?->getGroup()?->getCode());
        $cacheKey = implode(':', [
            spl_object_id($variant),
            spl_object_id($channel),
            $customer !== null ? spl_object_id($customer) : 'guest',
            $customerGroupCode,
            $b2bCustomer !== null ? 'b2b' : 'standard',
        ]);

        if (isset($this->ruleSets[$cacheKey])) {
            return $this->ruleSets[$cacheKey];
        }

        $regularRules = $this->findRulesForScopes(
            $variant,
            $channel,
            $groupCode !== '' ? ['', $groupCode] : [''],
        );
        $customerRules = $b2bCustomer !== null
            ? $this->findCustomerRules($variant, $channel, $b2bCustomer)
            : [];

        return $this->ruleSets[$cacheKey] = [
            'regularRules' => $regularRules,
            'customerRules' => $customerRules,
            'groupCode' => $groupCode,
        ];
    }

    /**
     * @return list<CustomerVariantPriceRule>
     */
    private function findCustomerRules(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        CustomerInterface $customer,
    ): array {
        /** @var list<CustomerVariantPriceRule> $rules */
        $rules = $this->entityManager
            ->createQueryBuilder()
            ->select('rule')
            ->from(CustomerVariantPriceRule::class, 'rule')
            ->andWhere('rule.variant = :variant')
            ->andWhere('rule.channelCode = :channelCode')
            ->andWhere('rule.customer = :customer')
            ->andWhere('rule.enabled = :enabled')
            ->setParameter('variant', $variant)
            ->setParameter('channelCode', (string) $channel->getCode())
            ->setParameter('customer', $customer)
            ->setParameter('enabled', true)
            ->orderBy('rule.minQuantity', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rules;
    }

    /**
     * @param list<string> $scopeCodes
     *
     * @return list<VariantPriceRule>
     */
    private function findRulesForScopes(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        array $scopeCodes,
    ): array {
        /** @var list<VariantPriceRule> $rules */
        $rules = $this->entityManager
            ->createQueryBuilder()
            ->select('rule')
            ->from(VariantPriceRule::class, 'rule')
            ->andWhere('rule.variant = :variant')
            ->andWhere('rule.channelCode = :channelCode')
            ->andWhere('rule.customerGroupCode IN (:scopeCodes)')
            ->andWhere('rule.enabled = :enabled')
            ->setParameter('variant', $variant)
            ->setParameter('channelCode', (string) $channel->getCode())
            ->setParameter('scopeCodes', $scopeCodes)
            ->setParameter('enabled', true)
            ->orderBy('rule.minQuantity', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rules;
    }
}
