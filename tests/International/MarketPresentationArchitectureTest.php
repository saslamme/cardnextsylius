<?php

declare(strict_types=1);

namespace App\Tests\International;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class MarketPresentationArchitectureTest extends TestCase
{
    public function testUtilityBarAndMobileHeaderExposeCentralMarketSelector(): void
    {
        $header = (string) file_get_contents(__DIR__ . '/../../templates/shop/layout/header/content.html.twig');
        self::assertSame(1, substr_count($header, "include 'shop/layout/header/_market_selector.html.twig'"));
        $utilityBar = (string) file_get_contents(__DIR__ . '/../../templates/shop/layout/header/top_bar.html.twig');
        self::assertSame(1, substr_count($utilityBar, "include 'shop/layout/header/_market_selector.html.twig'"));
        $selector = (string) file_get_contents(__DIR__ . '/../../templates/shop/layout/header/_market_selector.html.twig');
        self::assertStringContainsString('cardnext_market_links()', $selector);
        self::assertStringNotContainsString('https://', $selector);
    }

    public function testSeoAndSitemapUseMarketResolverWithoutStaticHosts(): void
    {
        $seo = (string) file_get_contents(__DIR__ . '/../../src/EventSubscriber/MarketSeoSubscriber.php');
        self::assertStringContainsString('hreflang', $seo);
        self::assertStringContainsString('MarketUrlResolver', $seo);
        $sitemap = (string) file_get_contents(__DIR__ . '/../../src/Controller/Shop/SitemapController.php');
        self::assertStringContainsString('currentMarket()', $sitemap);
        self::assertStringNotContainsString('www.cardnext.de', $sitemap);
    }

    public function testAllTranslationYamlFilesParseWithoutDuplicateKeys(): void
    {
        $files = glob(__DIR__ . '/../../translations/*.yaml');
        self::assertIsArray($files);
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            self::assertIsArray(Yaml::parseFile($file), $file);
        }
    }
}
