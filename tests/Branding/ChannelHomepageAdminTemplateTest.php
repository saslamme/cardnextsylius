<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use PHPUnit\Framework\TestCase;

final class ChannelHomepageAdminTemplateTest extends TestCase
{
    public function testTemplateShowsCustomPreviewAndFallbackStatus(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/admin/cardnext/homepage_content/edit.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('Individuelles Bild', $template);
        self::assertStringContainsString('Standardbild verwendet', $template);
        self::assertStringContainsString('asset(imagePath)', $template);
    }
}
