<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Channel\Channel;
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
        $publicRule = $this->regularRule('', 1, 800);
        $resolver = $this->resolverWithQueryResults([$publicRule]);

        self::assertSame(800, $resolver->resolve(new ProductVariant(), $this->channel(), 1, $customer));
    }

    public static function ineligibleCustomers(): iterable
    {
        yield 'guest' => [null];
        yield 'customer without profile' => [self::customer(null)];
        yield 'customer with disabled profile' => [self::customer(false)];
    }

    #[DataProvider('ineligibleCustomers')]
    public function testIndividualAndGroupRulesAreNotQueriedWithoutAnActiveB2bProfile(?Customer $customer): void
    {
        $resolver = $this->resolverWithQueryResults([null]);

        self::assertNull($resolver->resolve(new ProductVariant(), $this->channel(), 1, $customer));
    }

    public function testActiveB2bCustomerGetsIndividualPriceBeforeGroupAndPublicPrices(): void
    {
        $individualRule = $this->customerRule(1, 0);
        $resolver = $this->resolverWithQueryResults([$individualRule]);

        self::assertSame(0, $resolver->resolve(new ProductVariant(), $this->channel(), 1, self::customer(true)));
    }

    public function testActiveB2bCustomerGetsGroupPriceBeforePublicPrice(): void
    {
        $groupRule = $this->regularRule('retailers', 1, 700);
        $resolver = $this->resolverWithQueryResults([null, $groupRule]);

        self::assertSame(700, $resolver->resolve(new ProductVariant(), $this->channel(), 1, self::customer(true)));
    }

    public function testActiveB2bCustomerFallsBackToPublicPrice(): void
    {
        $publicRule = $this->regularRule('', 1, 800);
        $resolver = $this->resolverWithQueryResults([null, null, $publicRule]);

        self::assertSame(800, $resolver->resolve(new ProductVariant(), $this->channel(), 1, self::customer(true)));
    }

    public function testHighestApplicableMinimumQuantityRemainsTheResolvedRule(): void
    {
        $quantityRule = $this->regularRule('', 10, 600);
        $resolver = $this->resolverWithQueryResults([$quantityRule]);

        self::assertSame(600, $resolver->resolve(new ProductVariant(), $this->channel(), 12));
    }

    #[DataProvider('ineligibleCustomers')]
    public function testTierDisplayDoesNotReportIndividualOrGroupRulesForIneligibleCustomers(?Customer $customer): void
    {
        $resolver = $this->resolverWithQueryResults([], [[]]);
        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getChannelPricingForChannel')->willReturn(null);

        self::assertSame([], $resolver->getEffectiveTiers($variant, $this->channel(), $customer));
        self::assertFalse($resolver->hasEffectiveRules($variant, $this->channel(), $customer));
    }

    private function resolverWithQueryResults(array $singleResults, array $listResults = []): B2BPriceResolver
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQueryBuilder')->willReturnCallback(function () use (&$singleResults, &$listResults, $entityManager): QueryBuilder {
            $query = $this->getMockBuilder(Query::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getOneOrNullResult', 'getResult'])
                ->getMock();
            $query->method('getOneOrNullResult')->willReturnCallback(static function () use (&$singleResults) {
                return array_shift($singleResults);
            });
            $query->method('getResult')->willReturnCallback(static function () use (&$listResults): array {
                return array_shift($listResults) ?? [];
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

    private static function customer(?bool $profileEnabled): Customer
    {
        $customer = new Customer();
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

    private function regularRule(string $groupCode, int $minQuantity, int $price): VariantPriceRule
    {
        $rule = new VariantPriceRule();
        $rule->setCustomerGroupCode($groupCode);
        $rule->setMinQuantity($minQuantity);
        $rule->setPrice($price);

        return $rule;
    }

    private function customerRule(int $minQuantity, int $price): CustomerVariantPriceRule
    {
        $rule = new CustomerVariantPriceRule();
        $rule->setCustomer(self::customer(true));
        $rule->setMinQuantity($minQuantity);
        $rule->setPrice($price);

        return $rule;
    }
}
