<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use PHPUnit\Framework\TestCase;

final class HomepagePromoTemplateTest extends TestCase
{
    public function testPromoSectionIsEditorialAndConfigurationDriven(): void
    {
        $homepage = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/homepage/index.html.twig');
        $promo = (string) file_get_contents(__DIR__ . '/../../templates/shop/homepage/_promos.html.twig');
        $stylesheet = (string) file_get_contents(__DIR__ . '/../../assets/shop/styles/cardnext.css');

        self::assertGreaterThan(strpos($homepage, 'id="products"'), strpos($homepage, 'shop/homepage/_promos.html.twig'));
        self::assertStringContainsString('promo.enabled', $promo);
        self::assertStringContainsString('promo.imagePath', $promo);
        self::assertStringContainsString('promo.imageAlt', $promo);
        self::assertStringContainsString('promo.url', $promo);
        self::assertStringContainsString('promo.buttonLabel', $promo);
        self::assertStringContainsString('promo.badge', $promo);
        self::assertStringContainsString('cn-home-promo__media', $promo);
        self::assertStringContainsString('cn-home-promo__image', $promo);
        self::assertStringContainsString('cn-home-promos__intro', $promo);
        self::assertStringContainsString('cardnext.storefront.homepage.promos.kicker', $promo);
        self::assertStringContainsString('cardnext.storefront.homepage.promos.title', $promo);
        self::assertStringContainsString('cardnext.storefront.homepage.promos.text', $promo);
        self::assertStringNotContainsString('Kartendrucker', $promo);

        self::assertMatchesRegularExpression('/\\.cn-home-promo__media \\{[^}]*display: grid;[^}]*place-items: center;[^}]*overflow: hidden;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\\.cn-home-promo__image \\{[^}]*object-fit: contain;[^}]*object-position: center;/s', $stylesheet);
        self::assertDoesNotMatchRegularExpression('/\\.cn-home-promo(?:__media(?: img)?|__image) \\{[^}]*object-fit: cover;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\\.cn-home-promo \\{[^}]*grid-template-columns: minmax\\(0,1\\.25fr\\) minmax\\(0,1fr\\);[^}]*min-height: 400px;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\\.cn-home-promo__image \\{[^}]*width: 90%;[^}]*height: 88%;[^}]*max-width: 90%;[^}]*max-height: 88%;/s', $stylesheet);
        self::assertStringContainsString('@media (max-width: 1050px) { .cn-home-promos__grid { grid-template-columns: minmax(0,1fr); }', $stylesheet);
        self::assertStringContainsString('.cn-home-promo--text-only { grid-template-columns: minmax(0,1fr); }', $stylesheet);
        self::assertStringContainsString('.cn-home-promo { grid-template-columns: minmax(0,1fr); min-height: 0; }', $stylesheet);

        foreach (['da_DK', 'de', 'de_AT', 'en', 'es_ES', 'it_IT', 'nl_NL', 'sv_SE'] as $locale) {
            $translations = (string) file_get_contents(sprintf('%s/../../translations/messages.%s.yaml', __DIR__, $locale));
            self::assertStringContainsString('cardnext.storefront.homepage.promos.kicker', $translations);
            self::assertStringContainsString('cardnext.storefront.homepage.promos.title', $translations);
            self::assertStringContainsString('cardnext.storefront.homepage.promos.text', $translations);
        }
    }
}
