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
        self::assertStringContainsString('<div class="cn-advisor__image">', $template);
        self::assertStringContainsString("class: 'cn-advisor__product-image'", $template);
        self::assertStringContainsString('class="cn-advisor__image-placeholder"', $template);
        self::assertStringNotContainsString("class: 'img-fluid', filter: 'cardnext_product_card'", $template);

        self::assertIsString($stylesheet);
        self::assertMatchesRegularExpression('/\\.cn-advisor__image \\{[^}]*height: 190px;[^}]*overflow: hidden;[^}]*place-items: center;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\\.cn-advisor__product-image \\{[^}]*width: 100% !important;[^}]*height: 100% !important;[^}]*object-fit: contain !important;[^}]*object-position: center !important;/s', $stylesheet);
        self::assertStringContainsString('.cn-advisor__image-placeholder', $stylesheet);
    }
}
