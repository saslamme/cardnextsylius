<?php

declare(strict_types=1);

namespace App\Tests\Template;

use App\International\CardnextMarketRegistry;
use PHPUnit\Framework\TestCase;

final class QuoteCartTemplateTest extends TestCase
{
    public function testQuoteCartUsesOneFormAssociatedSubmitButtonAndLocalizedMoney(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/quote/cart.html.twig');
        self::assertIsString($template);

        self::assertStringContainsString("'id': 'cardnext-quote-request-form'", $template);
        self::assertStringContainsString('form="cardnext-quote-request-form"', $template);
        self::assertSame(1, substr_count($template, "'cardnext.quote.submit'|trans"));
        self::assertStringContainsString('sylius_format_money(currency, app.request.locale)', $template);
        self::assertSame(1, substr_count($template, 'form_widget(form.privacyConsent'));
        self::assertStringNotContainsString('form_row(form.privacyConsent)', $template);
    }

    public function testQuoteHeaderUsesTheSharedInlineIconMarkup(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/layout/header/content.html.twig');
        self::assertIsString($template);

        $quoteLink = substr($template, (int) strpos($template, 'cn-quote-header'), 900);
        self::assertStringContainsString('cn-shop-header__action-icon', $quoteLink);
        self::assertStringContainsString('viewBox="0 0 24 24"', $quoteLink);
        self::assertStringContainsString('stroke-width="1.8"', $quoteLink);
    }

    public function testQuoteHeaderUsesSharedConditionalBadgeAndCountlessLabel(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/layout/header/content.html.twig');
        self::assertIsString($template);

        self::assertSame(1, substr_count($template, 'cardnext_quote_cart_count()'));
        self::assertStringContainsString('{% if quoteCartCount > 0 %}', $template);
        self::assertStringContainsString('class="cn-shop-header__count-badge" aria-hidden="true"', $template);
        self::assertStringContainsString("cn-shop-header__action-label\">{{ 'cardnext.quote.cart'|trans }}", $template);
        self::assertStringNotContainsString("'cardnext.quote.header'|trans", $template);
    }

    public function testHeaderCounterCssIsSharedByBothCarts(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/assets/shop/styles/cardnext.css');
        self::assertIsString($css);

        self::assertStringContainsString('.cardnext-header .cn-shop-header__count-badge {', $css);
        self::assertStringContainsString('.cardnext-header .cn-shop-header__count-badge:empty {', $css);
        self::assertStringNotContainsString('.cn-shop-header__cart .cardnext-cart-badge {', $css);
    }

    public function testGermanAndAustrianChannelsHaveCountryDefaults(): void
    {
        $markets = new CardnextMarketRegistry();

        self::assertSame('DE', $markets->get('CARDNEXT_DE')?->countryCode);
        self::assertSame('AT', $markets->get('CARDNEXT_AT')?->countryCode);
    }
}
