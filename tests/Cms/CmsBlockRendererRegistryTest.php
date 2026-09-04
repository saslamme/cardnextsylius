<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Cms\CmsBlockRendererRegistry;
use PHPUnit\Framework\TestCase;

final class CmsBlockRendererRegistryTest extends TestCase
{
    public function testEveryTypeHasPredictableTemplate(): void
    {
        $registry = new CmsBlockRendererRegistry();

        foreach (CmsBlockRendererRegistry::TYPES as $type) {
            self::assertSame('shop/cms/block/_' . $type . '.html.twig', $registry->template($type));
        }
    }

    public function testProductSliderIsRegisteredAndValidatesItsConfiguration(): void
    {
        $registry = new CmsBlockRendererRegistry();

        self::assertContains('product_slider', CmsBlockRendererRegistry::TYPES);
        self::assertSame('shop/cms/block/_product_slider.html.twig', $registry->template('product_slider'));
        self::assertContains('productCodes is required.', $registry->validate('product_slider', []));
        self::assertContains('limit must be between 1 and 24.', $registry->validate('product_slider', ['productCodes' => ['A'], 'limit' => 0]));
        self::assertContains('limit must be between 1 and 24.', $registry->validate('product_slider', ['productCodes' => ['A'], 'limit' => 25]));
        self::assertContains('productCodes[1] must be a non-empty string.', $registry->validate('product_slider', ['productCodes' => ['A', ' '], 'limit' => 8]));
        self::assertSame([], $registry->validate('product_slider', ['productCodes' => ['A', 'B'], 'limit' => 8, 'showNavigation' => true]));
    }

    public function testRequiredFieldsAndUnsafeUrlsAreRejected(): void
    {
        $registry = new CmsBlockRendererRegistry();

        self::assertContains('headline is required.', $registry->validate('cta', [
            'buttonLabel' => 'Go',
            'buttonUrl' => 'javascript:alert(1)',
        ]));
        self::assertContains('buttonUrl is unsafe.', $registry->validate('cta', [
            'headline' => 'Hi',
            'buttonLabel' => 'Go',
            'buttonUrl' => 'javascript:alert(1)',
        ]));
    }

    public function testLinkCardsRequireItemsAndRejectUnsafeNestedUrls(): void
    {
        $registry = new CmsBlockRendererRegistry();

        self::assertContains('items is required.', $registry->validate('link_cards', []));
        self::assertContains('items[0].title is required.', $registry->validate('link_cards', [
            'items' => [['linkUrl' => '/downloads']],
        ]));
        self::assertContains('items[0].linkUrl is unsafe.', $registry->validate('link_cards', [
            'items' => [['title' => 'Support', 'linkUrl' => 'javascript:alert(1)']],
        ]));
        self::assertSame([], $registry->validate('link_cards', [
            'items' => [['title' => 'Downloads', 'linkUrl' => '/downloads']],
        ]));
    }
}
