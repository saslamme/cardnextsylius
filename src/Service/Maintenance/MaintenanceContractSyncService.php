<?php

declare(strict_types=1);

namespace App\Service\Maintenance;

use App\Entity\Customer\CustomerB2BProfile;
use App\Entity\Maintenance\MaintenanceContract;
use App\Integration\Erp\Maintenance\ErpMaintenanceContractData;
use App\Integration\Erp\Maintenance\ErpMaintenanceContractProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class MaintenanceContractSyncService
{
    public function __construct(private ErpMaintenanceContractProviderInterface $provider, private EntityManagerInterface $entityManager, private ClockInterface $clock, private LoggerInterface $logger)
    {
    }

    public function synchronize(): MaintenanceContractSyncResult
    {
        $fetched = $created = $updated = $unchanged = $skipped = $errors = 0;
        foreach ($this->provider->fetchAll() as $data) {
            ++$fetched;

            try {
                $this->validate($data);
                $profile = $this->entityManager->getRepository(CustomerB2BProfile::class)->findOneBy(['erpCustomerNumber' => trim($data->erpCustomerNumber)]);
                if (!$profile instanceof CustomerB2BProfile) {
                    ++$skipped;
                    $this->logger->warning('ERP maintenance contract customer is not mapped.', ['erpContractId' => $data->externalId, 'erpCustomerNumber' => $data->erpCustomerNumber]);

                    continue;
                }
                $contract = $this->entityManager->getRepository(MaintenanceContract::class)->findOneBy(['erpContractId' => trim($data->externalId)]);
                $now = \DateTimeImmutable::createFromInterface($this->clock->now());
                if (!$contract instanceof MaintenanceContract) {
                    $contract = new MaintenanceContract($data->externalId, $profile->getCustomer(), $data->erpCustomerNumber, $data->serialNumber, $data->startsAt, $data->endsAt, $now);
                    $contract->applyErpData($profile->getCustomer(), $data->externalId, $data->erpCustomerNumber, $data->serialNumber, $data->startsAt, $data->endsAt, $data->printerModel, $data->contractReference, $data->sourceUpdatedAt, $now);
                    $this->entityManager->persist($contract);
                    ++$created;
                } else {
                    $changed = $this->changed($contract, $data, $profile);
                    $contract->applyErpData($profile->getCustomer(), $data->externalId, $data->erpCustomerNumber, $data->serialNumber, $data->startsAt, $data->endsAt, $data->printerModel, $data->contractReference, $data->sourceUpdatedAt, $now);
                    $changed ? ++$updated : ++$unchanged;
                }
                $this->entityManager->flush();
            } catch (\Throwable $error) {
                ++$errors;
                $this->logger->error('ERP maintenance contract could not be synchronized.', ['erpContractId' => $data->externalId, 'erpCustomerNumber' => $data->erpCustomerNumber, 'errorType' => $error::class]);
            }
        }

        return new MaintenanceContractSyncResult($fetched, $created, $updated, $unchanged, $skipped, $errors);
    }

    private function validate(ErpMaintenanceContractData $data): void
    {
        if (trim($data->externalId) === '' || trim($data->erpCustomerNumber) === '' || trim($data->serialNumber) === '' || $data->endsAt < $data->startsAt) {
            throw new \UnexpectedValueException('Invalid ERP maintenance contract DTO.');
        }
    }

    private function changed(MaintenanceContract $contract, ErpMaintenanceContractData $data, CustomerB2BProfile $profile): bool
    {
        return $contract->getCustomer() !== $profile->getCustomer() || $contract->getErpCustomerNumber() !== trim($data->erpCustomerNumber) || $contract->getSerialNumber() !== trim($data->serialNumber) || $contract->getPrinterModel() !== $data->printerModel || $contract->getContractReference() !== $data->contractReference || $contract->getStartsAt() != $data->startsAt || $contract->getEndsAt() != $data->endsAt || $contract->getSourceUpdatedAt() != $data->sourceUpdatedAt;
    }
}
