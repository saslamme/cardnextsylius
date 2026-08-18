<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class PostOrderPositionCountTemplateTest extends TestCase
{
    /** @return iterable<string, array{list<object>, list<object>, int}> */
    public static function positionCounts(): iterable
    {
        yield 'normal only' => [[(object) []], [], 1];
        yield 'configured only' => [[], [(object) ['quantity' => 250]], 1];
        yield 'mixed' => [[(object) []], [(object) ['quantity' => 250]], 2];
        yield 'multiple normal and configured' => [[(object) [], (object) []], [(object) ['quantity' => 2], (object) ['quantity' => 500]], 4];
    }

    #[DataProvider('positionCounts')]
    public function testPositionCountUsesLinesRatherThanConfiguredQuantity(array $items, array $configuredItems, int $expected): void
    {
        $twig = new Environment(new ArrayLoader([
            'count' => '{{ order.items|length + order.configuredItems|length }}',
        ]), ['strict_variables' => true]);

        self::assertSame((string) $expected, $twig->render('count', [
            'order' => (object) ['items' => $items, 'configuredItems' => $configuredItems],
        ]));
    }

    public function testPostOrderTemplatesKeepOrderTotalAndDoNotRecalculatePositions(): void
    {
        $root = \dirname(__DIR__, 2);
        $templates = [
            (string) file_get_contents($root . '/templates/bundles/SyliusShopBundle/order/show/content/header.html.twig'),
            (string) file_get_contents($root . '/templates/bundles/SyliusAdminBundle/dashboard/index/component/new_orders.html.twig'),
        ];

        foreach ($templates as $template) {
            self::assertStringContainsString('order.items|length + order.configuredItems|length', $template);
            self::assertStringContainsString('order.total', $template);
            self::assertStringNotContainsString('configuredItemsTotal', $template);
            self::assertStringNotContainsString('configuredItem.quantity', $template);
        }
    }
}
