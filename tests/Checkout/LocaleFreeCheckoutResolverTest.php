<?php

declare(strict_types=1);

namespace App\Tests\Checkout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ShopBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\Yaml\Yaml;

final class LocaleFreeCheckoutResolverTest extends TestCase
{
    private const ROUTE_MAP = [
        'empty_order' => ['route' => 'sylius_shop_cart_summary'],
        'cart' => ['route' => 'sylius_shop_checkout_address'],
        'addressed' => ['route' => 'sylius_shop_checkout_select_shipping'],
        'shipping_selected' => ['route' => 'sylius_shop_checkout_select_payment'],
        'shipping_skipped' => ['route' => 'sylius_shop_checkout_select_payment'],
        'payment_selected' => ['route' => 'sylius_shop_checkout_complete'],
        'payment_skipped' => ['route' => 'sylius_shop_checkout_complete'],
    ];

    #[Test]
    #[DataProvider('localeFreeCheckoutPaths')]
    public function it_matches_locale_free_checkout_paths(string $path): void
    {
        self::assertTrue($this->matcher()->matches(Request::create($path)));
    }

    /** @return iterable<string, array{string}> */
    public static function localeFreeCheckoutPaths(): iterable
    {
        yield 'address' => ['/checkout/address'];
        yield 'shipping' => ['/checkout/select-shipping'];
        yield 'payment' => ['/checkout/select-payment'];
        yield 'complete' => ['/checkout/complete'];
    }

    #[Test]
    #[DataProvider('localePrefixedCheckoutPaths')]
    public function it_does_not_require_or_match_a_locale_prefix(string $path): void
    {
        self::assertFalse($this->matcher()->matches(Request::create($path)));
    }

    /** @return iterable<string, array{string}> */
    public static function localePrefixedCheckoutPaths(): iterable
    {
        yield 'German' => ['/de_DE/checkout/address'];
        yield 'Austrian German' => ['/de_AT/checkout/address'];
    }

    #[Test]
    public function application_override_preserves_the_sylius_checkout_route_map(): void
    {
        $configuration = $this->effectiveConfiguration();

        self::assertSame('^/checkout/.+', $configuration['checkout_resolver']['pattern']);
        self::assertSame(self::ROUTE_MAP, $configuration['checkout_resolver']['route_map']);
    }

    private function matcher(): PathRequestMatcher
    {
        $configuration = $this->effectiveConfiguration();

        return new PathRequestMatcher($configuration['checkout_resolver']['pattern']);
    }

    /** @return array<string, mixed> */
    private function effectiveConfiguration(): array
    {
        $root = \dirname(__DIR__, 2);
        $vendorConfiguration = Yaml::parseFile(
            $root . '/vendor/sylius/sylius/src/Sylius/Bundle/ShopBundle/Resources/config/app/config.yml',
        );
        $applicationConfiguration = Yaml::parseFile($root . '/config/packages/sylius_shop.yaml');

        return (new Processor())->processConfiguration(new Configuration(), [
            $vendorConfiguration['sylius_shop'],
            $applicationConfiguration['sylius_shop'],
        ]);
    }
}
