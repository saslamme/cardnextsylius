<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class StorefrontBrandCopyTest extends TestCase
{
    #[DataProvider('brandProvider')]
    public function testGermanWhyHeadingUsesTheResolvedBrandName(string $brandName, string $expected): void
    {
        $translator = new Translator('de');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', self::projectPath('translations/messages.de.yaml'), 'de');

        self::assertSame($expected, $translator->trans(
            'cardnext.storefront.homepage.why.kicker',
            ['%brand%' => $brandName],
        ));
    }

    public function testSharedTemplatesPassBrandingToVisibleBrandLabels(): void
    {
        $homepage = self::contents('templates/bundles/SyliusShopBundle/homepage/index.html.twig');
        $footer = self::contents('templates/shop/layout/footer/content.html.twig');

        self::assertStringContainsString("why.kicker'|trans({'%brand%': branding.brandName})", $homepage);
        self::assertStringContainsString("footer.company'|trans({'%brand%': branding.brandName})", $footer);
    }

    public function testFooterKeepsChannelAwareLogoAndBrandAlternativeText(): void
    {
        $footer = self::contents('templates/shop/layout/footer/content.html.twig');

        self::assertStringContainsString('asset(branding.logoDarkPath)', $footer);
        self::assertStringContainsString('alt="{{ branding.brandName }}"', $footer);
    }

    /** @return iterable<string, array{string, string}> */
    public static function brandProvider(): iterable
    {
        yield 'Cardnext' => ['Cardnext', 'Warum Cardnext'];
        yield 'Identible' => ['Identible', 'Warum Identible'];
        yield 'Inplastor' => ['Inplastor', 'Warum Inplastor'];
    }

    private static function contents(string $relativePath): string
    {
        $contents = file_get_contents(self::projectPath($relativePath));
        self::assertIsString($contents);

        return $contents;
    }

    private static function projectPath(string $relativePath): string
    {
        return dirname(__DIR__, 2) . '/' . $relativePath;
    }
}
