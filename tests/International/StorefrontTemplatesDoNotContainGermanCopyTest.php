<?php

declare(strict_types=1);

namespace App\Tests\International;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StorefrontTemplatesDoNotContainGermanCopyTest extends TestCase
{
    #[DataProvider('templateProvider')]
    public function testRemovedGermanCopyIsNotReintroduced(string $template): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $template);
        self::assertIsString($contents);

        foreach (['Professionelle Identifikationstechnik', 'Technologie für sichere Identitäten.', 'Beliebte Kategorien', 'Produkte suchen', 'Navigation öffnen', 'Deutschland · Deutsch'] as $copy) {
            self::assertStringNotContainsString($copy, $contents, sprintf('Translate customer-facing copy in %s.', $template));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function templateProvider(): iterable
    {
        foreach ([
            'templates/bundles/SyliusShopBundle/homepage/index.html.twig',
            'templates/shop/layout/header/content.html.twig',
            'templates/shop/layout/header/categories.html.twig',
            'templates/shop/layout/header/mobile_categories.html.twig',
            'templates/shop/layout/footer/content.html.twig',
        ] as $template) {
            yield $template => [$template];
        }
    }
}
