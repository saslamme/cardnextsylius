<?php
declare(strict_types=1);
namespace App\Tests\Maintenance;
use App\Entity\Customer\Customer;
use App\Entity\Maintenance\MaintenanceContract;
use App\Enum\Maintenance\MaintenanceContractStatus;
use PHPUnit\Framework\TestCase;
final class MaintenanceContractTest extends TestCase
{
    public function testInclusiveStatusBoundariesAndLocalNote(): void
    {
        $contract = new MaintenanceContract('ABC', new Customer(), '1001', ' SN123 ', new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), new \DateTimeImmutable('2025-01-01'));
        self::assertSame(MaintenanceContractStatus::Upcoming, $contract->statusAt(new \DateTimeImmutable('2025-12-31')));
        self::assertSame(MaintenanceContractStatus::Active, $contract->statusAt(new \DateTimeImmutable('2026-01-01')));
        self::assertSame(MaintenanceContractStatus::Active, $contract->statusAt(new \DateTimeImmutable('2026-06-30')));
        self::assertSame(MaintenanceContractStatus::Active, $contract->statusAt(new \DateTimeImmutable('2026-12-31')));
        self::assertSame(MaintenanceContractStatus::Expired, $contract->statusAt(new \DateTimeImmutable('2027-01-01')));
        self::assertSame('SN123', $contract->getSerialNumber());
        $contract->setInternalNote('Lokale Info');
        $contract->applyErpData($contract->getCustomer(), 'ABC', '1001', 'SN123', new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2027-12-31'), 'Model', null, null, new \DateTimeImmutable('2026-01-02'));
        self::assertSame('Lokale Info', $contract->getInternalNote());
    }
    public function testRejectsInvalidPeriod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MaintenanceContract('ABC', new Customer(), '1001', 'SN', new \DateTimeImmutable('2027-01-01'), new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable());
    }
}
