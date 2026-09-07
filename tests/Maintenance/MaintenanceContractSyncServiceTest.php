<?php

declare(strict_types=1);

namespace App\Tests\Maintenance;

use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerB2BProfile;
use App\Entity\Maintenance\MaintenanceContract;
use App\Entity\Maintenance\MaintenanceContractDevice;
use App\Integration\Erp\Maintenance\ErpMaintenanceContractData;
use App\Integration\Erp\Maintenance\ErpMaintenanceContractProviderInterface;
use App\Service\Maintenance\MaintenanceContractSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

final class MaintenanceContractSyncServiceTest extends TestCase
{
    public function testSynchronizesOnlyChangedDevicesAndKeepsExistingInstances(): void
    {
        $contract = $this->contract();
        $now = new \DateTimeImmutable('2026-09-07');
        $deviceA = new MaintenanceContractDevice($contract, 'A', $now);
        $deviceB = new MaintenanceContractDevice($contract, 'B', $now);
        $deviceC = new MaintenanceContractDevice($contract, 'C', $now);

        $this->invoke('synchronizeDevices', $contract, ['A', 'C', 'D'], $now->modify('+1 day'));

        self::assertSame(['A', 'C', 'D'], $contract->getSerialNumbers());
        self::assertSame($deviceA, $contract->getDevices()->get(0));
        self::assertSame($deviceC, $contract->getDevices()->get(2));
        self::assertFalse($contract->getDevices()->contains($deviceB));
        $lastDevice = $contract->getDevices()->last();
        self::assertInstanceOf(MaintenanceContractDevice::class, $lastDevice);
        self::assertEquals($now->modify('+1 day'), $lastDevice->getCreatedAt());
    }

    public function testDeviceOrderDoesNotCountAsChangeButAddRemoveAndHeaderChangesDo(): void
    {
        $contract = $this->contract();
        $now = new \DateTimeImmutable('2026-09-07');
        new MaintenanceContractDevice($contract, 'A', $now);
        new MaintenanceContractDevice($contract, 'B', $now);
        $profile = new CustomerB2BProfile();
        $profile->setCustomer($contract->getCustomer());

        self::assertFalse($this->changed($contract, $this->data(['B', 'A']), $profile));
        self::assertTrue($this->changed($contract, $this->data(['A', 'B', 'C']), $profile));
        self::assertTrue($this->changed($contract, $this->data(['A']), $profile));
        self::assertTrue($this->changed($contract, $this->data(['A', 'B'], 'Changed model'), $profile));
    }

    /** @param list<string> $serialNumbers */
    private function data(array $serialNumbers, ?string $printerModel = 'Model'): ErpMaintenanceContractData
    {
        return new ErpMaintenanceContractData('ERP-1', '1001', $serialNumbers, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), $printerModel, 'REF');
    }

    private function contract(): MaintenanceContract
    {
        $contract = new MaintenanceContract('ERP-1', new Customer(), '1001', new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), new \DateTimeImmutable('2025-01-01'));
        $contract->applyErpData($contract->getCustomer(), 'ERP-1', '1001', $contract->getStartsAt(), $contract->getEndsAt(), 'Model', 'REF', null, new \DateTimeImmutable('2025-01-01'));

        return $contract;
    }

    private function changed(MaintenanceContract $contract, ErpMaintenanceContractData $data, CustomerB2BProfile $profile): bool
    {
        $changed = $this->invoke('changed', $contract, $data, $profile);
        self::assertIsBool($changed);

        return $changed;
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $service = new MaintenanceContractSyncService(
            $this->createStub(ErpMaintenanceContractProviderInterface::class),
            $this->createStub(EntityManagerInterface::class),
            new MockClock(),
            new NullLogger(),
        );
        $reflection = new \ReflectionMethod($service, $method);

        return $reflection->invoke($service, ...$arguments);
    }
}
