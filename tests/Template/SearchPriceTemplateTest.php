<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class SearchPriceTemplateTest extends TestCase
{
    public function testFullResultsKeepTheExistingCardAndSharedQuantityAwarePricePresentation(): void
    {
        $search = $this->template('shop/search/index.html.twig');
        $card = $this->template('shop/category/product_card.html.twig');
        $price = $this->template('shop/product/_card_price.html.twig');

        self::assertStringContainsString("component('cardnext:product:card', {product: product})", $search);
        self::assertStringContainsString("include 'shop/product/_card_price.html.twig' with {variant: variant} only", $card);
        $this->assertQuantityAwarePriceWithNormalFallback($price);
    }

    public function testSuggestionsUseTheSharedPriceForTheExistingFirstEnabledVariantOnly(): void
    {
        $suggest = $this->template('shop/search/suggest.html.twig');
        $price = $this->template('shop/product/_card_price.html.twig');

        self::assertStringContainsString('{% set variant = product.enabledVariants|first %}', $suggest);
        self::assertStringContainsString("include 'shop/product/_card_price.html.twig' with {variant: variant} only", $suggest);
        self::assertStringNotContainsString('product.variants', $suggest);
        self::assertStringNotContainsString('cardnext_b2b_variant_from_price(product', $suggest);
        $this->assertQuantityAwarePriceWithNormalFallback($price);
    }

    public function testSearchLayoutAndProductLinksRemainUnchanged(): void
    {
        $search = $this->template('shop/search/index.html.twig');
        $suggest = $this->template('shop/search/suggest.html.twig');

        self::assertStringContainsString('class="cn-search-page__products"', $search);
        self::assertStringContainsString("path('cardnext_shop_search', {q: query, page: page + 1})", $search);
        self::assertStringContainsString('class="cn-search-suggest__product"', $suggest);
        self::assertStringContainsString('href="{{ cardnext_product_url(product) }}"', $suggest);
    }

    private function assertQuantityAwarePriceWithNormalFallback(string $price): void
    {
        self::assertStringContainsString('cardnext_b2b_variant_from_price(variant)', $price);
        self::assertStringContainsString('from_price.is_from_price', $price);
        self::assertStringContainsString('from_price.formatted_price', $price);
        self::assertStringContainsString("'cardnext.storefront.product_card.from_price'|trans", $price);
        self::assertStringContainsString("component('sylius_shop:product:card:price'", $price);
    }

    private function template(string $path): string
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/' . $path);
        self::assertIsString($template);

        return $template;
    }
}
