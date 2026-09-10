<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Maintenance\WertgarantieVariantResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WertgarantieVariantResolverTest extends TestCase
{
    /** @return iterable<string, array{int,int,int,int}> */
    public static function boundaries(): iterable
    {
        foreach (WertgarantieVariantResolver::LIMITS as $index => $limit) {
            yield (string) $limit => [$limit, $limit, WertgarantieVariantResolver::PRICES[WertgarantieVariantResolver::KOMFORT_3][$index], WertgarantieVariantResolver::PRICES[WertgarantieVariantResolver::KOMFORT_5][$index]];
        }
        yield '200.01' => [20001, 30000, 3990, 7990];
        yield '300.01' => [30001, 40000, 4990, 9990];
        yield '1000.01' => [100001, 130000, 12990, 17990];
    }

    #[DataProvider('boundaries')]
    public function testInclusiveMinorUnitBoundariesSelectRealVariants(int $devicePrice, int $expectedLimit, int $k3Price, int $k5Price): void
    {
        $channel = $this->channel();
        $resolver = new WertgarantieVariantResolver();
        $main = $this->mainVariant($channel, $devicePrice);
        $k3 = $resolver->resolve($this->tariff($channel, WertgarantieVariantResolver::KOMFORT_3, 'WERTGARANTIE_K3_'), $main, $channel);
        $k5 = $resolver->resolve($this->tariff($channel, WertgarantieVariantResolver::KOMFORT_5, 'WERTGARANTIE_K5_'), $main, $channel);

        $suffix = $expectedLimit === 1000000 ? '10000' : sprintf('%04d', intdiv($expectedLimit, 100));
        self::assertNotNull($k3);
        self::assertSame('WERTGARANTIE_K3_' . $suffix, $k3->getCode());
        self::assertSame($k3Price, $k3->getChannelPricingForChannel($channel)?->getPrice());
        self::assertNotNull($k5);
        self::assertSame('WERTGARANTIE_K5_' . $suffix, $k5->getCode());
        self::assertSame($k5Price, $k5->getChannelPricingForChannel($channel)?->getPrice());
    }

    public function testAboveMaximumIsUnavailable(): void
    {
        $channel = $this->channel();
        $tariff = $this->tariff($channel, WertgarantieVariantResolver::KOMFORT_5, 'WERTGARANTIE_K5_');
        self::assertNull((new WertgarantieVariantResolver())->resolve($tariff, $this->mainVariant($channel, 1000001), $channel));
    }

    private function channel(): Channel
    {
        $channel = new Channel();
        $channel->setCode('DE');

        return $channel;
    }

    private function tariff(Channel $channel, string $code, string $prefix): Product
    {
        $product = new Product();
        $product->setCode($code);
        $product->setEnabled(true);
        $product->setAddonOnly(true);
        $product->addChannel($channel);
        foreach (WertgarantieVariantResolver::LIMITS as $index => $limit) {
            $variant = new ProductVariant();
            $variant->setEnabled(true);
            $variant->setCode($prefix . ($limit === 1000000 ? '10000' : sprintf('%04d', intdiv($limit, 100))));
            $pricing = new ChannelPricing();
            $pricing->setChannelCode('DE');
            $pricing->setPrice(WertgarantieVariantResolver::PRICES[$code][$index]);
            $variant->addChannelPricing($pricing);
            $product->addVariant($variant);
        }

        return $product;
    }

    private function mainVariant(Channel $channel, int $price): ProductVariant
    {
        $variant = new ProductVariant();
        $variant->setEnabled(true);
        $pricing = new ChannelPricing();
        $pricing->setChannelCode((string) $channel->getCode());
        $pricing->setPrice($price);
        $variant->addChannelPricing($pricing);

        return $variant;
    }
}
