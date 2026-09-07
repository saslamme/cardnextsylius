<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Entity\Customer\Customer;
use App\Entity\Maintenance\MaintenanceContract;
use App\Entity\Maintenance\MaintenanceContractDevice;
use PHPUnit\Framework\TestCase;

final class MaintenanceContractDeviceTest extends TestCase
{
    public function testTrimsSerialNumberAndSetsTimestamps(): void
    {
        $now = new \DateTimeImmutable('2026-09-07 12:00:00');
        $device = new MaintenanceContractDevice($this->contract(), ' 883023120019 ', $now);

        self::assertSame('883023120019', $device->getSerialNumber());
        self::assertSame($now, $device->getCreatedAt());
        self::assertSame($now, $device->getUpdatedAt());
    }

    public function testRejectsEmptySerialNumber(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MaintenanceContractDevice($this->contract(), '  ', new \DateTimeImmutable());
    }

    private function contract(): MaintenanceContract
    {
        return new MaintenanceContract('1', new Customer(), '1001', new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), new \DateTimeImmutable());
    }
}
