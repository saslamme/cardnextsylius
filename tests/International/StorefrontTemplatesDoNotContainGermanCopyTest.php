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

        foreach (self::FORBIDDEN_COPY as $copy) {
            self::assertStringNotContainsString($copy, $contents, sprintf('Translate customer-facing copy in %s.', $template));
        }
    }

    private const FORBIDDEN_COPY = [
        'Professionelle Identifikationstechnik',
        'Technologie für sichere Identitäten.',
        'Beliebte Kategorien',
        'Produkte suchen',
        'Navigation öffnen',
        'Deutschland · Deutsch',
        'Filter anwenden',
        'Welcher Kartendrucker passt zu mir?',
        'Produktbild folgt',
        'Lieferbar',
        'Auf Anfrage',
        'Art.-Nr.',
        '>Details<',
        '>Vergleichen<',
        'Keine Produkte gefunden.',
    ];

    /** @return iterable<string, array{string}> */
    public static function templateProvider(): iterable
    {
        $paths = [
            'templates/bundles/SyliusShopBundle/homepage/index.html.twig',
            'templates/shop/layout/header/content.html.twig',
            'templates/shop/layout/header/categories.html.twig',
            'templates/shop/layout/header/mobile_categories.html.twig',
            'templates/shop/layout/footer/content.html.twig',
        ];
        foreach ([
            'templates/bundles/SyliusShopBundle/product',
            'templates/shop/category',
            'templates/shop/product',
            'templates/shop/search',
            'templates/shop/brand',
            'templates/shop/product_compare',
            'templates/shop/printer_advisor',
            'templates/shop/consumable_finder',
        ] as $directory) {
            foreach (glob(dirname(__DIR__, 2) . '/' . $directory . '/*.html.twig') ?: [] as $file) {
                $paths[] = substr($file, strlen(dirname(__DIR__, 2)) + 1);
            }
        }

        foreach (array_unique($paths) as $template) {
            yield $template => [$template];
        }
    }
}
