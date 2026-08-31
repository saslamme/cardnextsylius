<?php

declare(strict_types=1);

namespace App\Tests\International;

use App\International\CardnextMarketRegistry;
use App\International\MarketUrlResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MarketFoundationTest extends TestCase
{
    public function testAllSevenMarketMappingsAndHostResolution(): void
    {
        $registry = new CardnextMarketRegistry();
        $expected = [
            'CARDNEXT_DE' => ['www.cardnext.de', 'de_DE', 'EUR'],
            'CARDNEXT_AT' => ['at.cardnext.de', 'de_AT', 'EUR'],
            'CARDNEXT_DK' => ['dk.cardnext.de', 'da_DK', 'DKK'],
            'CARDNEXT_ES' => ['es.cardnext.de', 'es_ES', 'EUR'],
            'CARDNEXT_IT' => ['it.cardnext.de', 'it_IT', 'EUR'],
            'CARDNEXT_NL' => ['nl.cardnext.de', 'nl_NL', 'EUR'],
            'CARDNEXT_SE' => ['se.cardnext.de', 'sv_SE', 'SEK'],
        ];

        self::assertCount(7, $registry->all());
        foreach ($expected as $code => [$host, $locale, $currency]) {
            $market = $registry->get($code);
            self::assertNotNull($market);
            self::assertSame([$host, $locale, $currency], [$market->hostname, $market->localeCode, $market->currencyCode]);
            self::assertSame($code, $registry->forHostname($host)?->channelCode);
        }
        self::assertNull($registry->get('CARDNEXT_HU'));
        self::assertNull($registry->forHostname('unknown.cardnext.de'));
    }

    public function testSwitchingUsesTargetSchemeHostAndLocale(): void
    {
        $resolver = $this->resolver('CARDNEXT_DE');
        $request = Request::create('https://www.cardnext.de/de_DE/');
        $request->attributes->set('_route', 'sylius_shop_homepage');
        $request->attributes->set('_route_params', ['_locale' => 'de_DE']);

        $spain = (new CardnextMarketRegistry())->get('CARDNEXT_ES');
        self::assertNotNull($spain);
        self::assertSame('https://es.cardnext.de/es_ES/', $resolver->switchUrl($request, $spain));
    }

    public function testUnavailableProductFallsBackToTargetHomepage(): void
    {
        $resolver = $this->resolver('CARDNEXT_DE');
        $product = $this->createMock(ProductInterface::class);
        $product->method('isEnabled')->willReturn(false);
        $request = Request::create('https://www.cardnext.de/de_DE/a-product');
        $request->attributes->set('_route', 'sylius_shop_product_show');
        $request->attributes->set('_route_params', ['_locale' => 'de_DE', 'slug' => 'a-product']);
        $request->attributes->set('cardnext_product', $product);

        $denmark = (new CardnextMarketRegistry())->get('CARDNEXT_DK');
        self::assertNotNull($denmark);
        self::assertSame('https://dk.cardnext.de/da_DK/', $resolver->switchUrl($request, $denmark));
    }

    public function testCanonicalUsesCurrentChannelHostNotRequestHost(): void
    {
        $request = Request::create('https://attacker.invalid/es_ES/example?channel=CARDNEXT_DE');

        self::assertSame('https://es.cardnext.de/es_ES/example', $this->resolver('CARDNEXT_ES')->canonical($request));
    }

    private function resolver(string $channelCode): MarketUrlResolver
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn($channelCode);
        $context = $this->createMock(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);
        $repository = $this->createMock(RepositoryInterface::class);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static function (string $route, array $parameters): string {
            return $route === 'sylius_shop_homepage' ? '/' . $parameters['_locale'] . '/' : '/' . $parameters['_locale'] . '/' . ($parameters['slug'] ?? '');
        });

        return new MarketUrlResolver(new CardnextMarketRegistry(), $context, $repository, $router);
    }
}
