<?php

declare(strict_types=1);

namespace App\Tests\Bundle;

use PHPUnit\Framework\TestCase;

final class BundleArchitectureTest extends TestCase
{
    public function testProcessorRunsAfterB2bAndRebuildsAdjustments(): void
    {
        $b2b = file_get_contents(__DIR__.'/../../src/OrderProcessing/B2BPriceOrderProcessor.php');
        $bundle = file_get_contents(__DIR__.'/../../src/OrderProcessing/BundleDiscountOrderProcessor.php');
        self::assertIsString($b2b);
        self::assertIsString($bundle);
        self::assertStringContainsString("priority' => 49", $b2b);
        self::assertStringContainsString("priority' => 40", $bundle);
        self::assertStringContainsString('removeAdjustments(self::ADJUSTMENT_TYPE)', $bundle);
    }

    public function testBundleCartMetadataIsIndependentFromMaintenanceMetadata(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Entity/Order/OrderItem.php');
        self::assertIsString($source);
        self::assertStringContainsString('parentItem', $source);
        self::assertStringContainsString('bundleGroupKey', $source);
        self::assertStringContainsString('BUNDLE_ROLE_COMPONENT', $source);
    }

    public function testFrontendNeverPostsAClientDiscount(): void
    {
        $template = file_get_contents(__DIR__.'/../../templates/shop/product/_bundles.html.twig');
        self::assertIsString($template);
        self::assertStringNotContainsString('name="discount"', $template);
        self::assertStringContainsString("csrf_token('bundle_add_'", $template);
    }
}
