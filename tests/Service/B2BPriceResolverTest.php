<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerB2BProfile;
use App\Entity\Customer\CustomerGroup;
use App\Entity\Pricing\VariantTierPrice;
use App\Entity\Product\CustomerVariantPriceRule;
use App\Entity\Product\ProductVariant;
use App\Entity\Product\VariantPriceRule;
use App\Pricing\ResolvedVariantPrice;
use App\Pricing\VariantTierPriceResolver;
use App\Service\B2BPriceResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class B2BPriceResolverTest extends TestCase
{
    public function testPublicThresholdsAndChannelFallback(): void
    {
        $resolver = $this->resolver([], [$this->tier(10, 9500), $this->tier(25, 9000)]);
        $variant = $this->variant(10000); $channel = $this->channel();
        self::assertSame(10000, $resolver->resolve($variant, $channel, 9));
        self::assertSame(9500, $resolver->resolve($variant, $channel, 10));
        self::assertSame(9500, $resolver->resolve($variant, $channel, 24));
        self::assertSame(9000, $resolver->resolve($variant, $channel, 100));
        self::assertSame(ResolvedVariantPrice::CHANNEL_PRICING, $resolver->resolvePrice($variant, $channel, 1)?->source);
    }

    public function testLowestQuantityPriceOnlyReturnsAGenuineImprovement(): void
    {
        $variant = $this->variant(3600);
        $channel = $this->channel();

        $lower = $this->resolver([], [$this->tier(5, 3300), $this->tier(10, 3000)]);
        self::assertSame(
            ['min_quantity' => 10, 'price' => 3000, 'source' => ResolvedVariantPrice::PUBLIC_TIER],
            $lower->findLowestQuantityPrice($variant, $channel, 3600),
        );

        $same = $this->resolver([], [$this->tier(10, 3600)]);
        self::assertNull($same->findLowestQuantityPrice($variant, $channel, 3600));

        $higher = $this->resolver([], [$this->tier(10, 3900)]);
        self::assertNull($higher->findLowestQuantityPrice($variant, $channel, 3600));
    }

    public function testPromotedCatalogPriceIsNeverReplacedByAHigherTierPrice(): void
    {
        $resolver = $this->resolver([], [$this->tier(10, 3000)]);

        self::assertNull($resolver->findLowestQuantityPrice($this->variant(3600), $this->channel(), 2500));
    }

    public function testAnonymousVisitorGetsTheLowestPublicFromPrice(): void
    {
        $resolver = $this->resolver([], [$this->tier(5, 3300), $this->tier(10, 3000)]);

        self::assertSame(
            ['min_quantity' => 10, 'price' => 3000, 'source' => ResolvedVariantPrice::PUBLIC_TIER],
            $resolver->findLowestQuantityPrice($this->variant(3600), $this->channel(), 3600),
        );
    }

    public function testLoggedInCustomerGetsTheirLowestCustomerFromPrice(): void
    {
        $customer = $this->customer();
        $resolver = $this->resolver([
            [$this->customerRule($customer, 1, 2800), $this->customerRule($customer, 10, 2500)],
        ], [$this->tier(10, 3000)]);

        self::assertSame(
            ['min_quantity' => 10, 'price' => 2500, 'source' => ResolvedVariantPrice::CUSTOMER],
            $resolver->findLowestQuantityPrice($this->variant(3600), $this->channel(), 2800, $customer),
        );
    }

    public function testCustomerQuantityOnePricePreventsMisleadingPublicFromPrice(): void
    {
        $customer = $this->customer();
        $resolver = $this->resolver([
            [$this->customerRule($customer, 1, 2800)],
        ], [$this->tier(10, 3000)]);

        self::assertNull(
            $resolver->findLowestQuantityPrice($this->variant(3600), $this->channel(), 2800, $customer),
        );
    }

    public function testCustomerSourceWinsEvenWhenPublicIsCheaper(): void
    {
        $customer = $this->customer();
        $resolver = $this->resolver([[$this->customerRule($customer, 10, 9000)]], [$this->tier(1, 8000)]);
        self::assertSame(8000, $resolver->resolve($this->variant(10000), $this->channel(), 5, $customer));
        $result = $resolver->resolvePrice($this->variant(10000), $this->channel(), 10, $customer);
        self::assertSame(9000, $result?->price);
        self::assertSame(ResolvedVariantPrice::CUSTOMER, $result?->source);
    }

    public function testCustomerGroupWinsOverPublicTier(): void
    {
        $resolver = $this->resolver([[$this->groupRule(1, 9200)], []], [$this->tier(1, 8000)]);
        $result = $resolver->resolvePrice($this->variant(10000), $this->channel(), 50, $this->customer());
        self::assertSame(9200, $result?->price);
        self::assertSame(ResolvedVariantPrice::CUSTOMER_GROUP, $result?->source);
    }

    public function testAnonymousCustomerNeverLoadsIndividualRules(): void
    {
        $queries = 0; $resolver = $this->resolver([], [$this->tier(1, 8000)], $queries);
        self::assertSame(8000, $resolver->resolve($this->variant(10000), $this->channel(), 1));
        self::assertSame(0, $queries);
    }

    private function resolver(array $queryResults, array $tiers, int &$queries = 0): B2BPriceResolver
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturnCallback(function () use (&$queryResults, &$queries, $em): QueryBuilder {
            ++$queries; $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->onlyMethods(['getResult'])->getMock();
            $query->method('getResult')->willReturnCallback(static fn (): array => array_shift($queryResults) ?? []);
            $qb = $this->getMockBuilder(QueryBuilder::class)->setConstructorArgs([$em])->onlyMethods(['getQuery'])->getMock(); $qb->method('getQuery')->willReturn($query); return $qb;
        });
        $tierResolver = $this->createMock(VariantTierPriceResolver::class); $tierResolver->method('tiers')->willReturn($tiers);
        return new B2BPriceResolver($em, $tierResolver);
    }

    private function channel(): Channel { $channel = new Channel(); $channel->setCode('WEB'); return $channel; }
    private function variant(int $base): ProductVariant
    {
        $pricing = new ChannelPricing(); $pricing->setPrice($base);
        $variant = $this->createMock(ProductVariant::class); $variant->method('getChannelPricingForChannel')->willReturn($pricing); return $variant;
    }
    private function tier(int $quantity, int $price): VariantTierPrice { $tier = new VariantTierPrice(); $tier->setMinQuantity($quantity); $tier->setPrice($price); return $tier; }
    private function customer(): Customer
    {
        $customer = new Customer(); $customer->setEmail('buyer@example.com'); $group = new CustomerGroup(); $group->setCode('retailers'); $customer->setGroup($group);
        $profile = new CustomerB2BProfile(); $profile->setEnabled(true); $profile->setCustomer($customer); return $customer;
    }
    private function customerRule(Customer $customer, int $quantity, int $price): CustomerVariantPriceRule { $rule = new CustomerVariantPriceRule(); $rule->setCustomer($customer); $rule->setMinQuantity($quantity); $rule->setPrice($price); return $rule; }
    private function groupRule(int $quantity, int $price): VariantPriceRule { $rule = new VariantPriceRule(); $rule->setCustomerGroupCode('retailers'); $rule->setMinQuantity($quantity); $rule->setPrice($price); return $rule; }
}
