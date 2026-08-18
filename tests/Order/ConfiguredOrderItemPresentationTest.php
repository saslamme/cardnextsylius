<?php

declare(strict_types=1);

namespace App\Tests\Order;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ConfiguredOrderItemPresentationTest extends TestCase
{
    public function testSyliusOrderShowHooksAddConfiguredRowsAndTotals(): void
    {
        $root = \dirname(__DIR__, 2);
        $hooks = Yaml::parseFile($root . '/config/packages/cardnext_twig_hooks.yaml')['sylius_twig_hooks']['hooks'];

        self::assertSame(50, $hooks['sylius_admin.order.show.content.sections.items']['configured_items']['priority']);
        self::assertSame('admin/order/show/items_total.html.twig', $hooks['sylius_admin.order.show.content.sections.items.foot']['total']['template']);
        self::assertSame(250, $hooks['sylius_admin.order.show.content.sections.summary']['configured_items_total']['priority']);
    }

    public function testSnapshotMacroSupportsCurrentLegacyAndScalarValuesAndEscapesThem(): void
    {
        $root = \dirname(__DIR__, 2);
        $twig = new Environment(new FilesystemLoader($root . '/templates'), [
            'autoescape' => 'html',
            'strict_variables' => true,
        ]);
        $template = $twig->createTemplate("{% import 'shared/order/_configured_item_snapshot.html.twig' as snapshot %}{{ snapshot.render(entries, 'admin') }}");

        $html = $template->render(['entries' => [
            ['label' => 'Material', 'values' => [['name' => 'PVC weiß'], 'Recycling-PVC']],
            ['fieldName' => 'Druck', 'value' => ['name' => '4/4-farbig']],
            ['label' => 'Auflage', 'value' => 250],
            'Oberfläche' => '<script>alert(1)</script>',
            ['label' => 'Unvollständig'],
        ]]);

        self::assertStringContainsString('Material:', $html);
        self::assertStringContainsString('PVC weiß, Recycling-PVC', $html);
        self::assertStringContainsString('Druck:', $html);
        self::assertStringContainsString('4/4-farbig', $html);
        self::assertStringContainsString('Auflage:', $html);
        self::assertStringContainsString('250', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testTemplatesUseStoredAmountsAndKeepFinalOrderTotalAuthoritative(): void
    {
        $root = \dirname(__DIR__, 2);
        $mail = (string) file_get_contents($root . '/templates/bundles/SyliusShopBundle/email/order_confirmation.html.twig');
        $adminItems = (string) file_get_contents($root . '/templates/admin/order/show/configured_items.html.twig');
        $adminSummary = (string) file_get_contents($root . '/templates/admin/order/show/configured_items_total.html.twig');

        foreach ([$mail, $adminItems] as $template) {
            self::assertStringContainsString('item.unitAmount', $template);
            self::assertStringContainsString('item.quantity', $template);
            self::assertStringContainsString('item.total', $template);
            self::assertStringNotContainsString('item.unitAmount * item.quantity', $template);
        }

        self::assertStringContainsString('order.total|sylius_format_money', $mail);
        self::assertStringNotContainsString('order.total +', $mail);
        self::assertStringNotContainsString('order.total', $adminSummary);
    }
}
