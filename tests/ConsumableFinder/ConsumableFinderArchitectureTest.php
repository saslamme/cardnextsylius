<?php

declare(strict_types=1);

namespace App\Tests\ConsumableFinder;

use App\Entity\Product\ProductDeviceCompatibility;
use App\Repository\Product\ConsumableFinderRepository;
use PHPUnit\Framework\TestCase;

final class ConsumableFinderArchitectureTest extends TestCase
{
    public function testOnlyExplicitFinderCompatibilityTypesAreExposed(): void
    {
        self::assertSame([
            ProductDeviceCompatibility::TYPE_CONSUMABLE_FOR,
            ProductDeviceCompatibility::TYPE_CLEANING_FOR,
            ProductDeviceCompatibility::TYPE_ACCESSORY_FOR,
        ], ConsumableFinderRepository::finderTypes());
        self::assertNotContains(ProductDeviceCompatibility::TYPE_COMPATIBLE_WITH, ConsumableFinderRepository::finderTypes());
    }

    public function testResultQueryEnforcesAvailabilityAndEagerLoading(): void
    {
        $source = file_get_contents(__DIR__ . '/../../src/Repository/Product/ConsumableFinderRepository.php');
        self::assertIsString($source);
        foreach (['compatibility.enabled = true', 'product.enabled = true', "join('product.channels'", 'variant.enabled = true', 'pricing.channelCode = :channelCode', 'pricing.price IS NOT NULL', "leftJoin('product.images'", "select('compatibility', 'product', 'variant', 'pricing', 'images')"] as $constraint) {
            self::assertStringContainsString($constraint, $source);
        }
    }

    public function testStorefrontUsesCentralProductCardAndShareableSlug(): void
    {
        $template = file_get_contents(__DIR__ . '/../../templates/shop/consumable_finder/index.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString("component('cardnext:product:card'", $template);
        self::assertStringContainsString('name="device"', $template);
        self::assertStringContainsString('relation.verified', $template);
        self::assertStringContainsString('relation.note', $template);
    }
}
