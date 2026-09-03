<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class CategoryProductCardPriceTemplateTest extends TestCase
{
    public function testCategoryCardUsesOnlyItsVariantAndKeepsNormalPriceComponentAsFallback(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/category/product_card.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('cardnext_b2b_variant_from_price(variant)', $template);
        self::assertStringContainsString('from_price.is_from_price', $template);
        self::assertStringContainsString('from_price.formatted_price', $template);
        self::assertStringContainsString("component('sylius_shop:product:card:price'", $template);
        self::assertStringNotContainsString('product.variants', $template);
    }

    #[DataProvider('translationProvider')]
    public function testFromPriceIsTranslatedForEveryMaintainedStorefrontLocale(string $locale, string $translation): void
    {
        $messages = Yaml::parseFile(dirname(__DIR__, 2) . '/translations/messages.' . $locale . '.yaml');

        self::assertSame($translation, $messages['cardnext.storefront.product_card.from_price'] ?? null);
    }

    /** @return iterable<string, array{string,string}> */
    public static function translationProvider(): iterable
    {
        yield 'German' => ['de', 'ab'];
        yield 'Austrian German' => ['de_AT', 'ab'];
        yield 'English' => ['en', 'from'];
        yield 'Dutch' => ['nl_NL', 'vanaf'];
        yield 'Danish' => ['da_DK', 'fra'];
        yield 'Swedish' => ['sv_SE', 'från'];
        yield 'Italian' => ['it_IT', 'da'];
        yield 'Spanish' => ['es_ES', 'desde'];
    }
}
