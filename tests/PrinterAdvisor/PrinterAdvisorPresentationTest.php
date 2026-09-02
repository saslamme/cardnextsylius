<?php

declare(strict_types=1);

namespace Tests\PrinterAdvisor;

use PHPUnit\Framework\TestCase;

final class PrinterAdvisorPresentationTest extends TestCase
{
    public function testRecommendationImageContractKeepsRealImagesAndPlaceholdersInTheMediaFrame(): void
    {
        $root = dirname(__DIR__, 2);
        $template = file_get_contents($root.'/templates/shop/printer_advisor/index.html.twig');
        $stylesheet = file_get_contents($root.'/assets/shop/styles/cardnext.css');

        self::assertIsString($template);
        self::assertStringContainsString('<div class="cn-advisor__media">', $template);
        self::assertStringContainsString('class="cn-advisor__product-image"', $template);
        self::assertStringContainsString("recommendationImage.path|imagine_filter('cardnext_product_card')", $template);
        self::assertStringContainsString('class="cn-advisor__image-placeholder"', $template);
        self::assertStringNotContainsString("component('sylius_shop:main_image'", $template);

        self::assertIsString($stylesheet);
        self::assertMatchesRegularExpression('/\\.cn-advisor__media \\{[^}]*height: 220px;[^}]*overflow: hidden;[^}]*align-items: center;[^}]*justify-content: center;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\\.cn-advisor__product-image \\{[^}]*position: static !important;[^}]*width: 100% !important;[^}]*height: 100% !important;[^}]*object-fit: contain !important;[^}]*object-position: center !important;/s', $stylesheet);
        self::assertStringContainsString('.cn-advisor__image-placeholder', $stylesheet);
    }
}
