<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerB2BProfile;
use App\Entity\Customer\CustomerGroup;
use App\Entity\Product\CustomerVariantPriceRule;
use App\Entity\Product\ProductVariant;
use App\Entity\Product\VariantPriceRule;
use App\Service\B2BPriceResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class B2BPriceResolverTest extends TestCase
{
    #[DataProvider('ineligibleCustomers')]
    public function testOnlyPublicRulesApplyWithoutAnActiveB2bProfile(?Customer $customer): void
    {
        $resolver = $this->resolverWithRuleSets([[$this->regularRule('', 1, 800)]]);

        self::assertSame(800, $resolver->resolve(new ProductVariant(), $this->channel(), 1, $customer));
    }

    public static function ineligibleCustomers(): iterable
    {
        yield 'guest' => [null];
        yield 'customer without profile' => [self::customer(null)];
        yield 'customer with disabled profile' => [self::customer(false)];
    }

    public function testPriorityAndHighestApplicableQuantityArePreserved(): void
    {
        $public10 = $this->regularRule('', 10, 9500);
        $public50 = $this->regularRule('', 50, 8500);
        $group20 = $this->regularRule('retailers', 20, 8800);
        $customer30 = $this->customerRule(30, 0);
        $resolver = $this->resolverWithRuleSets([[$public10, $group20, $public50], [$customer30]]);
        $variant = new ProductVariant();
        $channel = $this->channel();
        $customer = self::customer(true);

        self::assertNull($resolver->resolve($variant, $channel, 9, $customer));
        self::assertSame(9500, $resolver->resolve($variant, $channel, 10, $customer));
        self::assertSame(8800, $resolver->resolve($variant, $channel, 20, $customer));
        self::assertSame(0, $resolver->resolve($variant, $channel, 30, $customer));
        self::assertSame(0, $resolver->resolve($variant, $channel, 50, $customer));
    }

    public function testPublicTierLadderRemainsUnchanged(): void
    {
        $resolver = $this->resolverWithRuleSets([[
            $this->regularRule('', 10, 9500),
            $this->regularRule('', 20, 9000),
            $this->regularRule('', 50, 8500),
        ]]);

        self::assertSame([
            $this->tier(1, 10000, 'base'),
            $this->tier(10, 9500, 'public'),
            $this->tier(20, 9000, 'public'),
            $this->tier(50, 8500, 'public'),
        ], $resolver->getEffectiveTiers($this->variantWithBasePrice(10000), $this->channel()));
    }

    public function testMixedTierLadderUsesPriorityAtEveryBreakpoint(): void
    {
        $customer = self::customer(true, 'buyer@example.com');
        $customerRule = $this->customerRule(30, 8200, $customer);
        $resolver = $this->resolverWithRuleSets([[
            $this->regularRule('', 10, 9500),
            $this->regularRule('retailers', 20, 8800),
            $this->regularRule('', 50, 8500),
        ], [$customerRule]]);

        self::assertSame([
            $this->tier(1, 10000, 'base'),
            $this->tier(10, 9500, 'public'),
            $this->tier(20, 8800, 'group', 'retailers'),
            $this->tier(30, 8200, 'customer', '', 'buyer@example.com'),
        ], $resolver->getEffectiveTiers($this->variantWithBasePrice(10000), $this->channel(), $customer));
    }

    public function testRuleSetsAreLoadedOnlyOnceAcrossAllResolverOperations(): void
    {
        $queryCount = 0;
        $resolver = $this->resolverWithRuleSets([
            [$this->regularRule('', 10, 9500)],
            [$this->customerRule(20, 9000)],
        ], $queryCount);
        $variant = $this->variantWithBasePrice(10000);
        $channel = $this->channel();
        $customer = self::customer(true);

        $resolver->resolve($variant, $channel, 1, $customer);
        $resolver->resolve($variant, $channel, 10, $customer);
        $resolver->resolveRule($variant, $channel, 20, $customer);
        self::assertTrue($resolver->hasEffectiveRules($variant, $channel, $customer));
        $resolver->getEffectiveTiers($variant, $channel, $customer);

        self::assertSame(2, $queryCount);
    }

    public function testGuestRuleSetIsLoadedOnlyOnce(): void
    {
        $queryCount = 0;
        $resolver = $this->resolverWithRuleSets([[$this->regularRule('', 10, 9500)]], $queryCount);
        $variant = $this->variantWithBasePrice(10000);
        $channel = $this->channel();

        $resolver->resolve($variant, $channel, 1);
        $resolver->resolve($variant, $channel, 10);
        $resolver->hasEffectiveRules($variant, $channel);
        $resolver->getEffectiveTiers($variant, $channel);

        self::assertSame(1, $queryCount);
    }

    private function resolverWithRuleSets(array $results, int &$queryCount = 0): B2BPriceResolver
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturnCallback(function () use (&$results, &$queryCount, $entityManager): QueryBuilder {
            ++$queryCount;
            $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->onlyMethods(['getResult'])->getMock();
            $query->method('getResult')->willReturnCallback(static function () use (&$results): array {
                return array_shift($results) ?? [];
            });
            $builder = $this->getMockBuilder(QueryBuilder::class)
                ->setConstructorArgs([$entityManager])
                ->onlyMethods(['getQuery'])
                ->getMock();
            $builder->method('getQuery')->willReturn($query);

            return $builder;
        });

        return new B2BPriceResolver($entityManager);
    }

    private static function customer(?bool $profileEnabled, string $email = 'customer@example.com'): Customer
    {
        $customer = new Customer();
        $customer->setEmail($email);
        $group = new CustomerGroup();
        $group->setCode('retailers');
        $customer->setGroup($group);

        if ($profileEnabled !== null) {
            $profile = new CustomerB2BProfile();
            $profile->setEnabled($profileEnabled);
            $profile->setCustomer($customer);
        }

        return $customer;
    }

    private function channel(): Channel
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        return $channel;
    }

    private function variantWithBasePrice(int $price): ProductVariant
    {
        $pricing = new ChannelPricing();
        $pricing->setPrice($price);
        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getChannelPricingForChannel')->willReturn($pricing);

        return $variant;
    }

    private function regularRule(string $groupCode, int $minQuantity, int $price): VariantPriceRule
    {
        $rule = new VariantPriceRule();
        $rule->setCustomerGroupCode($groupCode);
        $rule->setMinQuantity($minQuantity);
        $rule->setPrice($price);

        return $rule;
    }

    private function customerRule(int $minQuantity, int $price, ?Customer $customer = null): CustomerVariantPriceRule
    {
        $rule = new CustomerVariantPriceRule();
        $rule->setCustomer($customer ?? self::customer(true));
        $rule->setMinQuantity($minQuantity);
        $rule->setPrice($price);

        return $rule;
    }

    private function tier(
        int $quantity,
        int $price,
        string $source,
        string $groupCode = '',
        string $email = '',
    ): array {
        return [
            'min_quantity' => $quantity,
            'price' => $price,
            'source' => $source,
            'customer_group_code' => $groupCode,
            'customer_email' => $email,
        ];
    }
}
