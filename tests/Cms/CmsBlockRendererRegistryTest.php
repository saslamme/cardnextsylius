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
