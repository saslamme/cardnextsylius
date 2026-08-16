<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product\DeviceModel;
use App\Entity\Product\Product;
use App\Entity\Product\ProductDeviceCompatibility;
use PHPUnit\Framework\TestCase;

final class ProductDeviceCompatibilityTest extends TestCase
{
    public function testProductOwnsExplicitDeviceCompatibilityWithoutRequiringADeviceProduct(): void
    {
        $product = new Product();
        $device = new DeviceModel();
        $compatibility = new ProductDeviceCompatibility();
        $compatibility->setDeviceModel($device);
        $compatibility->setCompatibilityType(ProductDeviceCompatibility::TYPE_CONSUMABLE_FOR);
        $compatibility->setVerified(true);
        $product->addDeviceCompatibility($compatibility);
        self::assertSame($product, $compatibility->getProduct());
        self::assertSame($device, $compatibility->getDeviceModel());
        self::assertTrue($compatibility->isVerified());
        self::assertNull($device->getLinkedProduct());
    }

    public function testUnsupportedCompatibilityTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ProductDeviceCompatibility())->setCompatibilityType('guessed_from_frequency');
    }
}
