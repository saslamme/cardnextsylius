<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use PHPUnit\Framework\TestCase;

final class SeoLandingPageAdminUxTest extends TestCase
{
    public function testLocaleAndQualityControllersKeepTheEditorLive(): void
    {
        $locale = file_get_contents(__DIR__.'/../../assets/admin/controllers/seo_locale_controller.js');
        $quality = file_get_contents(__DIR__.'/../../assets/admin/controllers/seo_quality_controller.js');

        self::assertStringContainsString('dataset.locales', (string) $locale);
        self::assertStringContainsString("dispatchEvent(new Event('change'", (string) $locale);
        self::assertStringContainsString('400', (string) $quality);
        self::assertStringContainsString("[...this.value('metaTitle')].length", (string) $quality);
        self::assertStringContainsString("[...this.value('metaDescription')].length", (string) $quality);
        self::assertStringContainsString("this.field('robotsIndex')?.checked", (string) $quality);
        self::assertStringContainsString("this.field('robotsFollow')?.checked", (string) $quality);
    }
}
