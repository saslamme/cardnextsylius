<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use PHPUnit\Framework\TestCase;

final class CmsMultichannelSupportTemplateTest extends TestCase
{
    public function testDownloadCenterCopyIsBrandNeutralAndConfigurable(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/cms/block/_downloads.html.twig');

        self::assertIsString($source);
        self::assertStringContainsString("config.text|default('Handbücher, Treiber und technische Dokumente für Ihre Produkte.')", $source);
        self::assertStringNotContainsString('Cardnext Produkte', $source);
    }

    public function testLinkCardsRenderRelativeSupportAndDownloadLinksWithoutHardcodedBrand(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/templates/shop/cms/block/_link_cards.html.twig');

        self::assertIsString($source);
        self::assertStringContainsString('item.linkUrl', $source);
        self::assertStringContainsString('item.linkLabel', $source);
        self::assertStringNotContainsString('Cardnext', $source);
        self::assertStringNotContainsString('Identible', $source);
        self::assertStringNotContainsString('Inplastor', $source);
    }
}
