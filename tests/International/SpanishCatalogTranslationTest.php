<?php

declare(strict_types=1);

namespace App\Tests\International;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SpanishCatalogTranslationTest extends TestCase
{
    public function testSpanishCatalogUsesSpanishCopyWithoutGermanFallbacks(): void
    {
        $catalogue = Yaml::parseFile(dirname(__DIR__, 2) . '/translations/messages.es_ES.yaml');
        self::assertIsArray($catalogue);
        $storefront = $catalogue['cardnext']['storefront'] ?? null;
        self::assertIsArray($storefront);

        self::assertSame('Filtros', $storefront['catalog']['filters']);
        self::assertSame('Aplicar filtros', $storefront['catalog']['apply_filters']);
        self::assertStringContainsString('productos', $storefront['catalog']['product_count']);
        self::assertSame('Ordenar', $storefront['catalog']['sort']['label']);
        self::assertSame('Disponible', $storefront['product_card']['available']);
        self::assertSame('N.º de artículo', $storefront['product_card']['sku']);
        self::assertSame('Detalles', $storefront['product_card']['details']);

        $values = json_encode([$storefront['catalog'], $storefront['product_card']], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        foreach (['Filter anwenden', 'Produkte', 'Sortieren', 'Lieferbar', 'Art.-Nr.'] as $german) {
            self::assertStringNotContainsString($german, $values);
        }
    }
}
