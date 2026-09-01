<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use PHPUnit\Framework\TestCase;

final class ChannelBrandingAdminFormTest extends TestCase
{
    public function testBrandingFormContainsNavigationColorsAndExistingUploads(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $extension = file_get_contents($projectDir . '/src/Form/Extension/Admin/ChannelTypeExtension.php');
        $template = file_get_contents($projectDir . '/templates/admin/channel/form/sections/branding.html.twig');

        self::assertIsString($extension);
        self::assertIsString($template);

        foreach (['navigationBackgroundColor', 'navigationTextColor', 'navigationHoverColor', 'navigationBorderColor'] as $field) {
            self::assertStringContainsString("->add('{$field}'", $extension);
            self::assertStringContainsString("form.{$field}", $template);
        }

        self::assertStringContainsString('Optionales Hex-Format (#RGB oder #RRGGBB); leer verwendet den Cardnext-Standard.', $extension);

        foreach (['logoFile', 'logoDarkFile', 'faviconFile'] as $uploadField) {
            self::assertStringContainsString("->add('{$uploadField}'", $extension);
            self::assertStringContainsString("form.{$uploadField}", $template);
        }
    }
}
