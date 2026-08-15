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

final readonly class B2BPriceResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
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

        $b2bCustomer = $this->getActiveB2bCustomer($customer);

        if ($b2bCustomer !== null) {
            $customerRule = $this->findBestCustomerRule($variant, $channel, $quantity, $b2bCustomer);
            if ($customerRule instanceof CustomerVariantPriceRule) {
                return $customerRule;
            }
        }

        $groupCode = $b2bCustomer !== null
            ? trim((string) $b2bCustomer->getGroup()?->getCode())
            : '';

        if ($groupCode !== '') {
            $groupRule = $this->findBestGroupOrPublicRule($variant, $channel, $quantity, $groupCode);
            if ($groupRule instanceof VariantPriceRule) {
                return $groupRule;
            }
        }

        return $this->findBestGroupOrPublicRule($variant, $channel, $quantity, '');
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

        $b2bCustomer = $this->getActiveB2bCustomer($customer);
        $groupCode = $b2bCustomer !== null
            ? trim((string) $b2bCustomer->getGroup()?->getCode())
            : '';

        $regularRules = $this->findRulesForScopes(
            $variant,
            $channel,
            $groupCode !== '' ? ['', $groupCode] : [''],
        );

        $customerRules = $b2bCustomer !== null
            ? $this->findCustomerRules($variant, $channel, $b2bCustomer)
            : [];

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
        sort($breakpoints, SORT_NUMERIC);

        $tiers = [];
        $lastPrice = null;
        $lastSource = null;

        foreach ($breakpoints as $quantity) {
            $resolvedRule = $this->resolveRule($variant, $channel, $quantity, $customer);
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
        $b2bCustomer = $this->getActiveB2bCustomer($customer);

        if ($b2bCustomer !== null && $this->findCustomerRules($variant, $channel, $b2bCustomer) !== []) {
            return true;
        }

        $groupCode = $b2bCustomer !== null
            ? trim((string) $b2bCustomer->getGroup()?->getCode())
            : '';

        return $this->findRulesForScopes(
            $variant,
            $channel,
            $groupCode !== '' ? ['', $groupCode] : [''],
        ) !== [];
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

    private function findBestCustomerRule(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        int $quantity,
        CustomerInterface $customer,
    ): ?CustomerVariantPriceRule {
        /** @var CustomerVariantPriceRule|null $rule */
        $rule = $this->entityManager
            ->createQueryBuilder()
            ->select('rule')
            ->from(CustomerVariantPriceRule::class, 'rule')
            ->andWhere('rule.variant = :variant')
            ->andWhere('rule.channelCode = :channelCode')
            ->andWhere('rule.customer = :customer')
            ->andWhere('rule.enabled = :enabled')
            ->andWhere('rule.minQuantity <= :quantity')
            ->setParameter('variant', $variant)
            ->setParameter('channelCode', (string) $channel->getCode())
            ->setParameter('customer', $customer)
            ->setParameter('enabled', true)
            ->setParameter('quantity', $quantity)
            ->orderBy('rule.minQuantity', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $rule;
    }

    private function getActiveB2bCustomer(?CustomerInterface $customer): ?Customer
    {
        return $customer instanceof Customer && $customer->isB2bCustomer() ? $customer : null;
    }

    private function findBestGroupOrPublicRule(
        ProductVariantInterface $variant,
        ChannelInterface $channel,
        int $quantity,
        string $customerGroupCode,
    ): ?VariantPriceRule {
        /** @var VariantPriceRule|null $rule */
        $rule = $this->entityManager
            ->createQueryBuilder()
            ->select('rule')
            ->from(VariantPriceRule::class, 'rule')
            ->andWhere('rule.variant = :variant')
            ->andWhere('rule.channelCode = :channelCode')
            ->andWhere('rule.customerGroupCode = :customerGroupCode')
            ->andWhere('rule.enabled = :enabled')
            ->andWhere('rule.minQuantity <= :quantity')
            ->setParameter('variant', $variant)
            ->setParameter('channelCode', (string) $channel->getCode())
            ->setParameter('customerGroupCode', $customerGroupCode)
            ->setParameter('enabled', true)
            ->setParameter('quantity', $quantity)
            ->orderBy('rule.minQuantity', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $rule;
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
