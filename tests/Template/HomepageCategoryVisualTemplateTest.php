<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class HomepageCategoryVisualTemplateTest extends TestCase
{
    public function testCategoriesUseTheirExplicitIconOrPhotoVisual(): void
    {
        $homepage = (string) file_get_contents(__DIR__ . '/../../templates/bundles/SyliusShopBundle/homepage/index.html.twig');

        foreach ([
            'card_printers' => 'cardnext-icon-card-printer.svg',
            'plastic_cards' => 'cardnext-icon-cards.svg',
            'id_accessories' => 'cardnext-icon-card-holders.svg',
            'ribbons' => 'cardnext-icon-ribbon.svg',
            'barcode_scanners' => 'cardnext-icon-handheld-scanners.svg',
        ] as $code => $icon) {
            self::assertStringContainsString(sprintf("{'code': '%s', 'image': '%s', 'type': 'icon'}", $code, $icon), $homepage);
        }

        self::assertStringContainsString("{'code': 'rfid_readers', 'image': 'rfid-leser.webp', 'type': 'photo'}", $homepage);
        self::assertStringContainsString("{'code': 'access_control', 'image': 'zutrittskontrolle.webp', 'type': 'photo'}", $homepage);
        self::assertStringContainsString("'cardnext/homepage/categories/icons/'", $homepage);
        self::assertStringContainsString('cn-home-category__media--{{ category.type }}', $homepage);
        self::assertStringContainsString('cn-home-category__icon', $homepage);
        self::assertStringContainsString('cn-home-category__photo', $homepage);
    }

    public function testIconsContainWhilePhotosContinueToCover(): void
    {
        $stylesheet = (string) file_get_contents(__DIR__ . '/../../assets/shop/styles/cardnext.css');

        self::assertMatchesRegularExpression('/\.cn-home-category__photo \{[^}]*object-fit: cover;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\.cn-home-category__icon \{[^}]*object-fit: contain;/s', $stylesheet);
        self::assertDoesNotMatchRegularExpression('/\.cn-home-category__media--icon[^}]*object-fit: cover;/s', $stylesheet);
        self::assertDoesNotMatchRegularExpression('/\.cn-home-category__media img \{[^}]*object-fit: cover;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\.cn-home-category__media--icon \{[^}]*display: flex;[^}]*align-items: center;[^}]*justify-content: center;[^}]*overflow: visible;/s', $stylesheet);
        self::assertMatchesRegularExpression('/\.cn-home-category__icon \{[^}]*max-width: 75%;[^}]*max-height: 75%;/s', $stylesheet);
    }
}
