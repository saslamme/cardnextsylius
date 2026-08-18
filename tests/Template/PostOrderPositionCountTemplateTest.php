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
        yield 'normal only with quantity greater than one' => [[(object) ['quantity' => 5]], [], 1];
        yield 'configured only' => [[], [(object) ['quantity' => 250]], 1];
        yield 'mixed' => [[(object) ['quantity' => 5]], [(object) ['quantity' => 250]], 2];
        yield 'multiple normal and configured' => [[(object) [], (object) []], [(object) ['quantity' => 2], (object) ['quantity' => 500]], 4];
    }

    /**
     * @param list<object> $items
     * @param list<object> $configuredItems
     */
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

    public function testShopHeaderLabelsItsCountAsPositionsWithoutChangingSyliusItemsTranslation(): void
    {
        $root = \dirname(__DIR__, 2);
        $header = (string) file_get_contents($root . '/templates/bundles/SyliusShopBundle/order/show/content/header.html.twig');
        $germanTranslations = (string) file_get_contents($root . '/translations/messages.de.yaml');
        $englishTranslations = (string) file_get_contents($root . '/translations/messages.en.yaml');

        self::assertStringContainsString("positionCount == 1 ? 'cardnext.order.position' : 'cardnext.order.positions'", $header);
        self::assertStringNotContainsString("'sylius.ui.items'", $header);
        self::assertStringContainsString('position: Position', $germanTranslations);
        self::assertStringContainsString('positions: Positionen', $germanTranslations);
        self::assertStringContainsString('position: line', $englishTranslations);
        self::assertStringContainsString('positions: lines', $englishTranslations);
    }

    public function testAdminDashboardContinuesToCountOrderLines(): void
    {
        $root = \dirname(__DIR__, 2);
        $dashboard = (string) file_get_contents($root . '/templates/bundles/SyliusAdminBundle/dashboard/index/component/new_orders.html.twig');

        self::assertStringContainsString('order.items|length + order.configuredItems|length', $dashboard);
        self::assertStringNotContainsString('order.totalQuantity', $dashboard);
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
