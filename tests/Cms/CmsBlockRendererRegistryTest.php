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

    public function testVideoIsRegisteredWithItsLabelAndValidConfiguration(): void
    {
        $registry = new CmsBlockRendererRegistry();

        self::assertContains('video', CmsBlockRendererRegistry::TYPES);
        self::assertSame('Video', CmsBlockRendererRegistry::TYPE_LABELS['video']);
        self::assertSame([], $registry->validate('video', ['provider' => 'youtube', 'videoUrl' => 'https://youtu.be/abcdefghijk', 'aspectRatio' => '16:9']));
        self::assertSame([], $registry->validate('video', ['provider' => 'vimeo', 'videoUrl' => 'https://vimeo.com/123456789']));
    }

    public function testVideoConfigurationRejectsMissingOrUnsafeValues(): void
    {
        $registry = new CmsBlockRendererRegistry();

        self::assertContains('provider is required.', $registry->validate('video', ['videoUrl' => 'https://youtu.be/abcdefghijk']));
        self::assertContains('videoUrl is required.', $registry->validate('video', ['provider' => 'youtube']));
        self::assertContains('provider is invalid.', $registry->validate('video', ['provider' => 'other', 'videoUrl' => 'https://youtu.be/abcdefghijk']));
        self::assertContains('aspectRatio is invalid.', $registry->validate('video', ['provider' => 'youtube', 'videoUrl' => 'https://youtu.be/abcdefghijk', 'aspectRatio' => '2:1']));
        self::assertContains('videoUrl is not a valid URL for the selected provider.', $registry->validate('video', ['provider' => 'youtube', 'videoUrl' => 'https://evil.example/video']));
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

    public function testManufacturerSliderIsRegisteredAndValidated(): void
    {
        $registry = new CmsBlockRendererRegistry();
        self::assertContains('manufacturer_slider', CmsBlockRendererRegistry::TYPES);
        self::assertSame('Hersteller-Slider', CmsBlockRendererRegistry::TYPE_LABELS['manufacturer_slider']);
        self::assertContains('manufacturerCodes is required.', $registry->validate('manufacturer_slider', []));
        self::assertContains('manufacturerCodes must be a list.', $registry->validate('manufacturer_slider', ['manufacturerCodes' => 'ZEBRA']));
        self::assertContains('manufacturerCodes[1] must be a non-empty string.', $registry->validate('manufacturer_slider', ['manufacturerCodes' => ['ZEBRA', '']]));
        self::assertContains('limit must be between 1 and 24.', $registry->validate('manufacturer_slider', ['manufacturerCodes' => ['ZEBRA'], 'limit' => 25]));
        self::assertSame([], $registry->validate('manufacturer_slider', ['manufacturerCodes' => ['ZEBRA'], 'limit' => 12, 'showNavigation' => true, 'linkToManufacturer' => true]));
    }

    public function testGalleryIsRegisteredAndValidated(): void
    {
        $registry = new CmsBlockRendererRegistry();
        self::assertContains('gallery', CmsBlockRendererRegistry::TYPES);
        self::assertSame('Galerie', CmsBlockRendererRegistry::TYPE_LABELS['gallery']);
        self::assertContains('items is required.', $registry->validate('gallery', []));
        self::assertContains('items must be a list.', $registry->validate('gallery', ['items' => 'invalid']));
        self::assertContains('items[0].image is required.', $registry->validate('gallery', ['items' => [[]]]));
        foreach ([1, 5] as $columns) {
            self::assertContains('columns must be 2, 3 or 4.', $registry->validate('gallery', ['items' => [['image' => 'uploads/cms/a.jpg']], 'columns' => $columns]));
        }
        foreach ([2, 3, 4] as $columns) {
            self::assertSame([], $registry->validate('gallery', ['items' => [['image' => 'uploads/cms/a.webp', 'alt' => '', 'caption' => 'Test']], 'columns' => $columns]));
        }
    }

    public function testFeaturesAreRegisteredAndValidated(): void
    {
        $registry = new CmsBlockRendererRegistry();
        self::assertContains('features', CmsBlockRendererRegistry::TYPES);
        self::assertSame('Vorteile / USP', CmsBlockRendererRegistry::TYPE_LABELS['features']);
        self::assertSame('shop/cms/block/_features.html.twig', $registry->template('features'));

        $valid = ['items' => [['icon' => 'consulting', 'title' => 'Beratung', 'text' => 'Persönlich.']]];
        foreach ([2, 3, 4] as $columns) {
            self::assertSame([], $registry->validate('features', $valid + ['columns' => $columns]));
        }
        self::assertContains('items is required.', $registry->validate('features', []));
        self::assertContains('items must be a list.', $registry->validate('features', ['items' => 'invalid']));
        self::assertContains('items[0] is invalid.', $registry->validate('features', ['items' => ['invalid']]));
        self::assertContains('items[0].title is required.', $registry->validate('features', ['items' => [['text' => 'Text']]]));
        self::assertContains('items[0].icon is invalid.', $registry->validate('features', ['items' => [['title' => 'Titel', 'icon' => 'fa-user']]]));
        foreach ([1, 5] as $columns) {
            self::assertContains('columns must be 2, 3 or 4.', $registry->validate('features', $valid + ['columns' => $columns]));
        }
    }

    public function testStatsAreRegisteredAndKeepValuesAsStrings(): void
    {
        $registry = new CmsBlockRendererRegistry();
        self::assertContains('stats', CmsBlockRendererRegistry::TYPES);
        self::assertSame('Zahlen & Fakten', CmsBlockRendererRegistry::TYPE_LABELS['stats']);
        self::assertSame('shop/cms/block/_stats.html.twig', $registry->template('stats'));

        foreach (['20+', '2.000+', '24/7', '< 24 h', '99,9 %'] as $value) {
            self::assertSame([], $registry->validate('stats', ['items' => [['value' => $value, 'label' => 'Kennzahl']], 'columns' => 4]));
        }
        self::assertContains('items is required.', $registry->validate('stats', []));
        self::assertContains('items[0].value is required.', $registry->validate('stats', ['items' => [['label' => 'Produkte']]]));
        self::assertContains('items[0].label is required.', $registry->validate('stats', ['items' => [['value' => '20+']]]));
        self::assertContains('columns must be 2, 3 or 4.', $registry->validate('stats', ['items' => [['value' => '20+', 'label' => 'Hersteller']], 'columns' => 1]));
    }

    public function testTestimonialsAreRegisteredAndRequireAnAuthor(): void
    {
        $registry = new CmsBlockRendererRegistry();
        self::assertContains('testimonials', CmsBlockRendererRegistry::TYPES);
        self::assertSame('Kundenstimmen', CmsBlockRendererRegistry::TYPE_LABELS['testimonials']);
        self::assertSame('shop/cms/block/_testimonials.html.twig', $registry->template('testimonials'));

        foreach ([
            ['quote' => 'Sehr gut.', 'company' => 'Beispiel GmbH'],
            ['quote' => 'Sehr gut.', 'name' => 'Max'],
            ['quote' => 'Sehr gut.', 'name' => 'Max', 'role' => 'IT', 'company' => 'Beispiel GmbH'],
        ] as $item) {
            foreach ([1, 2, 3] as $columns) {
                self::assertSame([], $registry->validate('testimonials', ['items' => [$item], 'columns' => $columns]));
            }
        }
        self::assertContains('items is required.', $registry->validate('testimonials', []));
        self::assertContains('items[0].quote is required.', $registry->validate('testimonials', ['items' => [['name' => 'Max']]]));
        self::assertContains('items[0] requires a name or company.', $registry->validate('testimonials', ['items' => [['quote' => 'Anonym']]]));
        self::assertContains('columns must be 1, 2 or 3.', $registry->validate('testimonials', ['items' => [['quote' => 'Gut', 'name' => 'Max']], 'columns' => 4]));
    }
}
