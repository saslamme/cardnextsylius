<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ProductSearchSynonymsAdminTest extends TestCase
{
    private const TEMPLATE = 'admin/cardnext/product/translations/search_synonyms.html.twig';

    #[DataProvider('productTranslationHookProvider')]
    public function testItAddsSearchSynonymsToProductTranslationHooks(string $hook): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = Yaml::parseFile($root . '/config/packages/cardnext_product_search_synonyms.yaml');
        self::assertIsArray($configuration);
        self::assertIsArray($configuration['sylius_twig_hooks']);
        $hooks = $configuration['sylius_twig_hooks']['hooks'];
        self::assertIsArray($hooks);
        self::assertIsArray($hooks[$hook]);
        self::assertIsArray($hooks[$hook]['cardnext_search_synonyms']);

        self::assertSame(self::TEMPLATE, $hooks[$hook]['cardnext_search_synonyms']['template']);
        self::assertSame(-100, $hooks[$hook]['cardnext_search_synonyms']['priority']);
    }

    /** @return iterable<string, array{string}> */
    public static function productTranslationHookProvider(): iterable
    {
        yield 'product create' => ['sylius_admin.product.create.content.form.sections.translations'];
        yield 'product update' => ['sylius_admin.product.update.content.form.sections.translations'];
    }

    public function testTemplateRendersSearchSynonymsForTheHookLocale(): void
    {
        $root = dirname(__DIR__, 2);
        $template = file_get_contents($root . '/templates/' . self::TEMPLATE);

        self::assertIsString($template);
        self::assertStringContainsString('hookable_metadata.context.form', $template);
        self::assertStringContainsString('hookable_metadata.context.locale', $template);
        self::assertStringContainsString('form.searchSynonyms', $template);
        self::assertStringContainsString("sylius_test_form_attribute('search-synonyms', locale)", $template);
    }

    public function testNoSyliusAdminVendorTemplateIsOverridden(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root . '/templates/bundles/SyliusAdminBundle/product');
    }
}
