<?php

declare(strict_types=1);

namespace App\Tests\Leasing;

use PHPUnit\Framework\TestCase;

final class LeasingBuyboxPresentationTest extends TestCase
{
    public function testLeasingIsHookedAfterTheCompletePurchaseArea(): void
    {
        $hooks = $this->projectFile('config/packages/cardnext_product_layout.yaml');

        self::assertStringContainsString("'sylius_shop.product.show.content.info.summary':", $hooks);
        self::assertMatchesRegularExpression('/leasing:\s+template: \'shop\/product\/_leasing.html.twig\'\s+(?:#[^\n]*\s+)*priority: -200/', $hooks);
        self::assertStringNotContainsString("'sylius_shop.product.show.content.info':\n            leasing:", $hooks);
    }

    public function testLeasingUsesAQuietSecondaryPresentation(): void
    {
        $template = $this->projectFile('templates/shop/product/_leasing.html.twig');
        $styles = $this->projectFile('assets/shop/styles/cardnext.css');

        self::assertStringContainsString("'cardnext.leasing.for_business_customers'|trans", $template);
        self::assertStringContainsString('cn-btn cn-btn--secondary cardnext-leasing__cta', $template);
        self::assertStringNotContainsString('cardnext-leasing__badge', $template);
        self::assertStringNotContainsString('cn-btn--primary cardnext-leasing__cta', $template);
        self::assertMatchesRegularExpression('/\.cardnext-leasing \{[^}]*background: var\(--cn-surface-softer\);[^}]*border: 1px solid var\(--cn-border\);[^}]*box-shadow: none;/', $styles);
        self::assertStringNotContainsString('border-top: 3px solid var(--cn-primary)', $styles);
        self::assertStringNotContainsString('linear-gradient(135deg,#fff 0%,var(--cn-surface-softer) 100%)', $styles);
        self::assertStringContainsString('data-cn-leasing-rate', $template);
        self::assertStringContainsString('?duration={{ preferred.factor.durationMonths }}', $template);
    }

    private function projectFile(string $path): string
    {
        $contents = file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
