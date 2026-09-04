<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class CmsVideoTemplateTest extends TestCase
{
    public function testVideoTemplateUsesOnlyTheServerResolvedResponsiveEmbed(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../templates/shop/cms/block/_video.html.twig');

        self::assertStringContainsString('cardnext_cms_video_embed', $template);
        self::assertStringContainsString('cn-cms-video__media--', $template);
        self::assertStringContainsString('src="{{ embed.embedUrl }}"', $template);
        self::assertStringNotContainsString('src="{{ config.videoUrl', $template);
        self::assertStringContainsString('loading="lazy"', $template);
        self::assertStringContainsString('title="{{', $template);
        self::assertStringContainsString('allowfullscreen', $template);
        self::assertStringContainsString('{% if embed %}', $template);
    }
}
