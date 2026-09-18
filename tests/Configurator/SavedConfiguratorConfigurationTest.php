<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Entity\Channel\Channel;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\SavedConfiguratorConfiguration;
use PHPUnit\Framework\TestCase;

final class SavedConfiguratorConfigurationTest extends TestCase
{
    public function testEntityCanBeProxiedByDoctrine(): void
    {
        $reflection = new \ReflectionClass(SavedConfiguratorConfiguration::class);

        self::assertFalse($reflection->isFinal());
    }

    public function testSnapshotIsImmutableAndTokenHasAtLeast128BitsOfEntropy(): void
    {
        $tokens = [];
        for ($i = 0; $i < 1000; ++$i) {
            $token = SavedConfiguratorConfiguration::generateToken();
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}$/', $token);
            $tokens[$token] = true;
        }
        self::assertCount(1000, $tokens);

        $configurator = new Configurator('cards', 'Cards');
        $channel = new Channel();
        $snapshot = new SavedConfiguratorConfiguration(array_key_first($tokens), $configurator, $channel, 'de_DE', 500, ['material' => 'PVC', 'extras' => [], 'rounded' => true, 'motifs' => 3], 'standard');

        self::assertSame(['quantity' => 500, 'selections' => ['material' => 'PVC', 'extras' => [], 'rounded' => true, 'motifs' => 3], 'leadTimeCode' => 'standard'], $snapshot->configuration());
        self::assertFalse(method_exists($snapshot, 'setSelections'));
        self::assertFalse(method_exists($snapshot, 'setToken'));
    }

    public function testStorefrontContainsSaveRestoreAndSeoContracts(): void
    {
        $product = (string) file_get_contents(__DIR__.'/../../templates/shop/configurator/product.html.twig');
        $page = (string) file_get_contents(__DIR__.'/../../templates/shop/configurator/page.html.twig');
        $javascript = (string) file_get_contents(__DIR__.'/../../assets/shop/configurator.js');

        self::assertStringContainsString('data-save-endpoint', $product);
        self::assertStringContainsString('hasRestoredValue', $product);
        self::assertStringContainsString('restoring ? null', $product);
        self::assertStringContainsString("shareUrl.search = '';", $javascript);
        self::assertStringContainsString('JSON.stringify(calculatedPayload)', $javascript);
        self::assertStringContainsString("navigator.clipboard?.writeText", $javascript);
        self::assertStringContainsString("'noindex,follow'", $page);
        self::assertStringContainsString("path('cardnext_shop_configurator_page'", $page);
    }
}
