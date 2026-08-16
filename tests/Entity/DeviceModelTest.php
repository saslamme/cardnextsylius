<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Product\DeviceModel;
use App\Entity\Product\DeviceModelAlias;
use App\Entity\Product\Manufacturer;
use PHPUnit\Framework\TestCase;

final class DeviceModelTest extends TestCase
{
    public function testAliasesNormalizeFormattingVariantsToTheSameLookupValue(): void
    {
        $values = [];
        foreach (['Zebra ZC300', 'Zebra ZC 300', 'Zebra ZC-300'] as $value) {
            $alias = new DeviceModelAlias();
            $alias->setAlias($value);
            $values[] = $alias->getNormalizedAlias();
        }
        self::assertSame(['ZEBRAZC300', 'ZEBRAZC300', 'ZEBRAZC300'], $values);
    }

    public function testAliasMaintainsItsOwningDeviceRelation(): void
    {
        $manufacturer = new Manufacturer();
        $manufacturer->setCode('ZEBRA');
        $manufacturer->setName('Zebra');
        $device = new DeviceModel();
        $device->setManufacturer($manufacturer);
        $device->setName('ZC300');
        $alias = new DeviceModelAlias();
        $alias->setAlias('ZC 300');
        $device->addAlias($alias);
        self::assertSame($device, $alias->getDeviceModel());
        self::assertTrue($device->getAliases()->contains($alias));
    }
}
