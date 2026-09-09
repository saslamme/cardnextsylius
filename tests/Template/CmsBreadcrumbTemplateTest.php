<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class CmsBreadcrumbTemplateTest extends TestCase
{
    public function testCmsPageUsesSharedStorefrontBreadcrumbWithItsTranslatedTitle(): void
    {
        $page = $this->file('templates/shop/cms/show.html.twig');

        self::assertStringContainsString("include 'shop/_breadcrumb.html.twig'", $page);
        self::assertStringContainsString('items: [{label: translation.title}]', $page);
        self::assertStringContainsString('cardnext_cms_breadcrumb_structured_data(translation.title', $page);
        self::assertLessThan(strpos($page, '<main'), strpos($page, "include 'shop/_breadcrumb.html.twig'"));
    }

    public function testSharedBreadcrumbUsesExistingStylesAndChannelAwareHomepageRoute(): void
    {
        $breadcrumb = $this->file('templates/shop/_breadcrumb.html.twig');

        self::assertStringContainsString('cn-breadcrumbs', $breadcrumb);
        self::assertStringContainsString('cn-container cn-breadcrumbs__inner', $breadcrumb);
        self::assertStringContainsString('cn-breadcrumbs__sep', $breadcrumb);
        self::assertStringContainsString("path('sylius_shop_homepage')", $breadcrumb);
        self::assertStringNotContainsString('http://', $breadcrumb);
        self::assertStringNotContainsString('https://', $breadcrumb);
        self::assertStringContainsString('aria-current="page"', $breadcrumb);
    }

    public function testHomeLabelIsLocalizedForSupportedLocales(): void
    {
        $expected = ['de' => 'Startseite', 'en' => 'Home', 'nl_NL' => 'Home'];
        foreach ($expected as $locale => $label) {
            $messages = Yaml::parse($this->file(sprintf('translations/messages.%s.yaml', $locale)));
            self::assertSame($label, $messages['cardnext']['storefront']['common']['home']);
        }
    }

    public function testHomepageTemplateDoesNotRenderBreadcrumb(): void
    {
        $homepage = $this->file('templates/bundles/SyliusShopBundle/homepage/index.html.twig');

        self::assertStringNotContainsString('shop/_breadcrumb.html.twig', $homepage);
        self::assertStringNotContainsString('cn-breadcrumbs', $homepage);
    }

    public function testCatalogBreadcrumbTemplatesRemainUnchangedAndIndependent(): void
    {
        self::assertStringContainsString('taxon.parent', $this->file('templates/shop/category/breadcrumbs.html.twig'));
        self::assertStringContainsString('product.name', $this->file('templates/bundles/SyliusShopBundle/product/show/content/header/breadcrumbs.html.twig'));
    }

    private function file(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
