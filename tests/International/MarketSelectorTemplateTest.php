<?php

declare(strict_types=1);

namespace App\Tests\International;

use App\International\CardnextMarketRegistry;
use App\International\MarketDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class MarketSelectorTemplateTest extends TestCase
{
    #[DataProvider('cardnextMarkets')]
    public function testCardnextMarketRendersSelector(string $channelCode): void
    {
        $registry = new CardnextMarketRegistry();
        $market = $registry->get($channelCode);

        self::assertNotNull($market);
        $html = $this->renderSelector($market, $registry);
        self::assertStringContainsString('data-cardnext-market-selector', $html);
        self::assertStringContainsString($market->countryDisplayName, $html);
    }

    #[DataProvider('nonCardnextBrands')]
    public function testNonCardnextBrandDoesNotRenderSelectorOrResolveLinks(string $channelCode, string $hostname): void
    {
        $registry = new CardnextMarketRegistry();

        self::assertNull($registry->get($channelCode));
        self::assertNull($registry->forHostname($hostname));
        self::assertSame('', trim($this->renderSelector(null, $registry)));
    }

    /** @return iterable<string, array{string}> */
    public static function cardnextMarkets(): iterable
    {
        yield 'Cardnext Germany' => ['CARDNEXT_DE'];
        yield 'Cardnext Austria' => ['CARDNEXT_AT'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function nonCardnextBrands(): iterable
    {
        yield 'Identible' => ['IDENTIBLE_DE', 'identible.cardnext.de'];
        yield 'Inplastor' => ['INPLASTOR_AT', 'inplastor.cardnext.de'];
    }

    private function renderSelector(?MarketDefinition $currentMarket, CardnextMarketRegistry $registry): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('cardnext_current_market', static fn (): ?MarketDefinition => $currentMarket));
        $twig->addFunction(new TwigFunction('cardnext_market_links', static function () use ($currentMarket, $registry): array {
            self::assertNotNull($currentMarket, 'Market links must not be resolved outside a Cardnext market.');

            return array_map(
                static fn (MarketDefinition $market): array => ['market' => $market, 'url' => $market->baseUrl()],
                array_values($registry->all()),
            );
        }));

        return $twig->render('shop/layout/header/_market_selector.html.twig');
    }
}
