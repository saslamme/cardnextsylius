<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\TestCase;

final class AccountBreadcrumbTemplateTest extends TestCase
{
    private const STANDARD_ACCOUNT_ROUTES = [
        'sylius_shop_account_dashboard',
        'sylius_shop_account_order_index',
        'sylius_shop_account_order_show',
        'sylius_shop_account_address_book_index',
        'sylius_shop_account_address_book_create',
        'sylius_shop_account_address_book_update',
        'sylius_shop_account_profile_update',
        'sylius_shop_account_change_password',
    ];

    public function testAccountContentRendersExactlyOneDirectBreadcrumbOutsideTheContentHook(): void
    {
        $content = $this->readProjectFile('templates/bundles/SyliusShopBundle/account/common/content.html.twig');

        self::assertSame(1, substr_count($content, "include 'shop/account/_sylius_breadcrumb.html.twig'"));
        self::assertStringNotContainsString("hook 'breadcrumbs'", $content);
        self::assertLessThan(strpos($content, "hook 'content'"), strpos($content, "include 'shop/account/_sylius_breadcrumb.html.twig'"));
    }

    public function testAccountLayoutDoesNotReattachBreadcrumbsToNestedHooks(): void
    {
        $configuration = $this->readProjectFile('config/packages/cardnext_account_layout.yaml');

        self::assertDoesNotMatchRegularExpression('/^[[:space:]]*[\'\"][^\'\"]+\.content\.breadcrumbs[\'\"]:/m', $configuration);
        self::assertSame(count(self::STANDARD_ACCOUNT_ROUTES) + 3, substr_count($configuration, "enabled: false"));
    }

    public function testCentralBreadcrumbMapsEveryStandardAccountRouteAndAlwaysRendersFallback(): void
    {
        $breadcrumb = $this->readProjectFile('templates/shop/account/_sylius_breadcrumb.html.twig');

        foreach (self::STANDARD_ACCOUNT_ROUTES as $route) {
            self::assertStringContainsString("route == '$route'", $breadcrumb, sprintf('Route %s is not mapped.', $route));
        }

        self::assertStringContainsString('{% set items = [] %}', $breadcrumb);
        self::assertSame(1, substr_count($breadcrumb, "include 'shop/account/_breadcrumb.html.twig'"));
        self::assertStringContainsString("with { items: items }", $breadcrumb);
    }

    private function readProjectFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
