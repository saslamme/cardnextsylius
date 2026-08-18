<?php

declare(strict_types=1);

namespace App\Tests\Template;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

final class InternalOrderNotificationTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'), [
            'autoescape' => 'html',
        ]);
        $this->twig->addFilter(new TwigFilter(
            'sylius_format_money',
            static fn (int $amount, string $currency, string $locale): string => sprintf('[%d %s %s]', $amount, $currency, $locale),
        ));
    }

    public function testNormalOnlyOrderKeepsItsItemCountAndSummary(): void
    {
        $html = $this->render([$this->normalItem('Normaler Drucker', 89900)], [], 89900);

        self::assertStringContainsString('Normaler Drucker', $html);
        self::assertStringContainsString('Positionen', $html);
        self::assertMatchesRegularExpression('/Positionen\s*<div[^>]*>\s*1\s*<\/div>/', $html);
        self::assertStringContainsString('Artikel', $html);
        self::assertStringContainsString('[89900 EUR de_DE]', $html);
        self::assertStringNotContainsString('Konfiguriertes Produkt', $html);
        self::assertStringNotContainsString('Konfigurierte Artikel', $html);
    }

    public function testConfiguredOnlyOrderUsesStoredPresentationAndAmounts(): void
    {
        $configuredItem = $this->configuredItem(
            'Plastikkarten bedrucken',
            250,
            171,
            42750,
            ['material' => ['label' => 'Material', 'values' => [['name' => 'PVC weiß']]]],
            'Standard',
            7,
        );

        $html = $this->render([], [$configuredItem], 48640);

        self::assertMatchesRegularExpression('/Positionen\s*<div[^>]*>\s*1\s*<\/div>/', $html);
        self::assertStringContainsString('Plastikkarten bedrucken', $html);
        self::assertStringContainsString('Konfiguriertes Produkt', $html);
        self::assertStringContainsString('<strong>Material:</strong> PVC weiß', $html);
        self::assertStringContainsString('Standard ·', $html);
        self::assertStringContainsString('ca. 7 Arbeitstage', $html);
        self::assertStringContainsString('250 ×', $html);
        self::assertStringContainsString('[171 EUR de_DE] / Stück', $html);
        self::assertSame(2, substr_count($html, '[42750 EUR de_DE]'));
        self::assertStringNotContainsString('Zwischensumme', $html);
        self::assertStringContainsString('[48640 EUR de_DE]', $html);
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function positionCountProvider(): iterable
    {
        yield 'mixed order ignores configured quantity' => [1, 1, 2];
        yield 'two normal plus two configured lines' => [2, 2, 4];
    }

    #[DataProvider('positionCountProvider')]
    public function testPositionCountUsesLinesRatherThanQuantity(int $normalCount, int $configuredCount, int $expected): void
    {
        $normalItems = [];
        for ($index = 1; $index <= $normalCount; ++$index) {
            $normalItems[] = $this->normalItem('Normal ' . $index, 1000);
        }

        $configuredItems = [];
        for ($index = 1; $index <= $configuredCount; ++$index) {
            $configuredItems[] = $this->configuredItem('Configured ' . $index, 250, 171, 42750);
        }

        $html = $this->render($normalItems, $configuredItems, 100000);

        self::assertMatchesRegularExpression(sprintf('/Positionen\s*<div[^>]*>\s*%d\s*<\/div>/', $expected), $html);
        foreach (array_merge($normalItems, $configuredItems) as $item) {
            $name = $item->productName ?? $item->configuratorName;
            self::assertSame(1, substr_count($html, $name));
        }
    }

    public function testLegacySnapshotIsDefensiveAndSnapshotValuesAreEscaped(): void
    {
        $injection = '<script>alert(1)</script>';
        $snapshot = [
            'legacy' => 'Alter Wert',
            'missing_values' => ['label' => 'Unvollständig'],
            'unsafe' => ['fieldName' => $injection, 'value' => $injection],
            'unexpected' => null,
        ];

        $html = $this->render([], [$this->configuredItem($injection, 1, 100, 100, $snapshot)]);

        self::assertStringContainsString('<strong>legacy:</strong> Alter Wert', $html);
        self::assertStringNotContainsString($injection, $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('Unvollständig:', $html);
    }

    public function testMissingProductionTimeDoesNotRenderAnEmptyBlock(): void
    {
        $html = $this->render([], [$this->configuredItem('Ohne Produktionszeit', 1, 100, 100)]);

        self::assertStringNotContainsString('>Produktionszeit</div>', $html);
    }

    public function testMixedSummarySeparatesStoredItemTotalsAndUsesOrderTotalUnchanged(): void
    {
        $html = $this->render(
            [$this->normalItem('Normal', 89900)],
            [$this->configuredItem('Configured', 250, 121, 30250)],
            126040,
        );

        self::assertMatchesRegularExpression('/Artikel<\/td>.*?\[89900 EUR de_DE\]/s', $html);
        self::assertMatchesRegularExpression('/Konfigurierte Artikel<\/td>.*?\[30250 EUR de_DE\]/s', $html);
        self::assertMatchesRegularExpression('/Gesamt<\/td>.*?\[126040 EUR de_DE\]/s', $html);
        self::assertSame(2, substr_count($html, '[30250 EUR de_DE]'));
        self::assertSame(2, substr_count($html, '[126040 EUR de_DE]'));
    }

    /**
     * @param list<object> $normalItems
     * @param list<object> $configuredItems
     */
    private function render(array $normalItems, array $configuredItems, int $total = 100): string
    {
        $order = (object) [
            'billingAddress' => null,
            'shippingAddress' => null,
            'shipments' => [],
            'payments' => [],
            'customer' => null,
            'number' => 'CN-123',
            'items' => $normalItems,
            'configuredItems' => $configuredItems,
            'itemsTotal' => array_sum(array_map(static fn (object $item): int => $item->total, $normalItems)),
            'shippingTotal' => 590,
            'taxTotal' => 0,
            'total' => $total,
            'currencyCode' => 'EUR',
            'localeCode' => 'de_DE',
            'notes' => null,
        ];

        return $this->twig->render('email/internal_order_notification.html.twig', ['order' => $order]);
    }

    private function normalItem(string $name, int $total): object
    {
        return (object) [
            'productName' => $name,
            'variant' => null,
            'quantity' => 1,
            'total' => $total,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function configuredItem(
        string $name,
        int $quantity,
        int $unitAmount,
        int $total,
        array $snapshot = [],
        ?string $leadTimeName = null,
        ?int $workingDays = null,
    ): object {
        return (object) [
            'configuratorName' => $name,
            'quantity' => $quantity,
            'unitAmount' => $unitAmount,
            'total' => $total,
            'selectionsSnapshot' => $snapshot,
            'leadTimeName' => $leadTimeName,
            'workingDays' => $workingDays,
        ];
    }
}
