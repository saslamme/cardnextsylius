<?php

declare(strict_types=1);

namespace App\Tests\International;

use App\Entity\Product\Product;
use App\Entity\Product\ProductTranslation;
use App\Entity\Taxonomy\Taxon;
use App\Entity\Taxonomy\TaxonTranslation;
use App\International\CardnextMarketRegistry;
use App\International\MarketUrlResolver;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
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

    public function testProductAlternatesUseEveryExactLocalizedSlug(): void
    {
        $slugs = [
            'de_DE' => 'de-produkt', 'de_AT' => 'at-produkt', 'da_DK' => 'dk-produkt',
            'es_ES' => 'es-producto', 'it_IT' => 'it-prodotto', 'nl_NL' => 'nl-product', 'sv_SE' => 'se-produkt',
        ];
        [$product, $channels] = $this->productAndChannels($slugs);
        $request = Request::create('https://es.cardnext.de/es_ES/wrong-incoming-slug?tracking=1');
        $request->attributes->set('cardnext_product', $product);
        $resolver = $this->resolver('CARDNEXT_ES', $channels);

        self::assertSame([
            'de-DE' => 'https://www.cardnext.de/de_DE/de-produkt',
            'de-AT' => 'https://at.cardnext.de/de_AT/at-produkt',
            'da-DK' => 'https://dk.cardnext.de/da_DK/dk-produkt',
            'es-ES' => 'https://es.cardnext.de/es_ES/es-producto',
            'it-IT' => 'https://it.cardnext.de/it_IT/it-prodotto',
            'nl-NL' => 'https://nl.cardnext.de/nl_NL/nl-product',
            'sv-SE' => 'https://se.cardnext.de/sv_SE/se-produkt',
        ], $this->alternateMap($resolver, $request));
        self::assertSame('https://es.cardnext.de/es_ES/es-producto', $resolver->canonical($request));
    }

    public function testSeoOmitsUnavailableAndUntranslatedProductsWhileSwitcherFallsBack(): void
    {
        [$product, $channels] = $this->productAndChannels(['de_DE' => 'de-produkt', 'es_ES' => 'es-producto']);
        $sweden = array_pop($channels);
        self::assertInstanceOf(ChannelInterface::class, $sweden);
        $product->removeChannel($sweden);
        $channels[] = $sweden;
        $request = Request::create('https://es.cardnext.de/es_ES/es-producto');
        $request->attributes->set('cardnext_product', $product);
        $resolver = $this->resolver('CARDNEXT_ES', $channels);
        $markets = new CardnextMarketRegistry();
        $swedishMarket = $markets->get('CARDNEXT_SE');
        self::assertNotNull($swedishMarket);

        self::assertSame(['de-DE', 'es-ES'], array_keys($this->alternateMap($resolver, $request)));
        self::assertSame('https://se.cardnext.de/sv_SE/', $resolver->switchUrl($request, $swedishMarket));
        self::assertArrayNotHasKey('da-DK', $this->alternateMap($resolver, $request));
    }

    public function testTaxonAlternatesUseLocalizedSlugsInEachChannelTree(): void
    {
        $root = new Taxon();
        $root->setCode('CATALOG');
        $taxon = new Taxon();
        $taxon->setCode('PRINTERS');
        $root->addChild($taxon);
        (new \ReflectionProperty($taxon, 'root'))->setValue($taxon, $root);
        foreach (['de_DE' => 'drucker', 'es_ES' => 'impresoras'] as $locale => $slug) {
            $translation = new TaxonTranslation();
            $translation->setLocale($locale);
            $translation->setSlug($slug);
            $taxon->addTranslation($translation);
        }
        $channels = $this->channels($root);
        $request = Request::create('https://es.cardnext.de/es_ES/impresoras');
        $request->attributes->set('cardnext_taxon', $taxon);

        self::assertSame([
            'de-DE' => 'https://www.cardnext.de/de_DE/drucker',
            'es-ES' => 'https://es.cardnext.de/es_ES/impresoras',
        ], $this->alternateMap($this->resolver('CARDNEXT_ES', $channels), $request));
    }

    /** @param list<ChannelInterface> $availableChannels */
    private function resolver(string $channelCode, array $availableChannels = []): MarketUrlResolver
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn($channelCode);
        $context = $this->createMock(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);
        $repository = $this->createMock(RepositoryInterface::class);
        $repository->method('findBy')->willReturn($availableChannels);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?ChannelInterface => current(array_filter(
            $availableChannels,
            static fn (ChannelInterface $candidate): bool => $candidate->getCode() === ($criteria['code'] ?? null),
        )) ?: null);
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static function (string $route, array $parameters): string {
            return $route === 'sylius_shop_homepage' ? '/' . $parameters['_locale'] . '/' : '/' . $parameters['_locale'] . '/' . ($parameters['slug'] ?? '');
        });

        return new MarketUrlResolver(new CardnextMarketRegistry(), $context, $repository, $router);
    }

    /** @param array<string, string> $slugs
     * @return array{Product, list<ChannelInterface>}
     */
    private function productAndChannels(array $slugs): array
    {
        $product = new Product();
        $product->setCode('STABLE_PRODUCT_CODE');
        $product->setEnabled(true);
        foreach ($slugs as $locale => $slug) {
            $translation = new ProductTranslation();
            $translation->setLocale($locale);
            $translation->setSlug($slug);
            $product->addTranslation($translation);
        }
        $channels = $this->channels();
        foreach ($channels as $channel) {
            $product->addChannel($channel);
        }

        return [$product, $channels];
    }

    /** @return list<ChannelInterface> */
    private function channels(?TaxonInterface $menuTaxon = null): array
    {
        $channels = [];
        foreach ((new CardnextMarketRegistry())->all() as $market) {
            $channel = $this->createMock(ChannelInterface::class);
            $channel->method('getCode')->willReturn($market->channelCode);
            $channel->method('isEnabled')->willReturn(true);
            $channel->method('getMenuTaxon')->willReturn($menuTaxon);
            $channels[] = $channel;
        }

        return $channels;
    }

    /** @return array<string, string> */
    private function alternateMap(MarketUrlResolver $resolver, Request $request): array
    {
        $map = [];
        foreach ($resolver->alternateLinks($request) as $link) {
            $map[$link['market']->hreflang()] = $link['url'];
        }

        return $map;
    }
}
