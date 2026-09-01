<?php

declare(strict_types=1);

namespace App\Tests\International;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SwedishProductDetailTranslationTest extends TestCase
{
    #[DataProvider('translationProvider')]
    public function testProductDetailCopyIsNaturalSwedish(string $key, string $expected, string $german): void
    {
        $catalogue = Yaml::parseFile(dirname(__DIR__, 2) . '/translations/messages.sv_SE.yaml');
        self::assertIsArray($catalogue);

        $value = $catalogue;
        foreach (explode('.', $key) as $segment) {
            self::assertIsArray($value);
            self::assertArrayHasKey($segment, $value);
            $value = $value[$segment];
        }

        self::assertSame($expected, $value);
        self::assertNotSame($german, $value);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function translationProvider(): iterable
    {
        yield 'product code' => ['cardnext.storefront.product_detail.product_code', 'Produktkod', 'Produktcode'];
        yield 'price' => ['cardnext.storefront.product_detail.pricing.your_price', 'Ditt pris', 'Ihr Preis'];
        yield 'availability' => ['cardnext.storefront.product_detail.stock.variant_availability', 'Tillgänglighet för denna variant', 'Verfügbarkeit für diese Variante'];
        yield 'cart' => ['cardnext.storefront.product_detail.add_to_cart', 'Lägg i varukorgen', 'In den Warenkorb'];
        yield 'comparison' => ['cardnext.storefront.product_detail.add_to_compare', 'Lägg till i jämförelse', 'Zum Vergleich hinzufügen'];
        yield 'description' => ['cardnext.storefront.product_detail.tabs.description', 'Beskrivning', 'Beschreibung'];
        yield 'specifications' => ['cardnext.storefront.product_detail.tabs.specifications', 'Tekniska data', 'Technische Daten'];
        yield 'compatibility' => ['cardnext.storefront.product_detail.tabs.compatibility', 'Kompatibilitet', 'Kompatibilität'];
    }
}
