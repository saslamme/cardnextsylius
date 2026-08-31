<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Maintenance\MaintenanceContractSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cardnext:erp:sync-maintenance-contracts', description: 'Synchronize the local maintenance-contract projection from ERP.')]
final class CardnextSyncMaintenanceContractsCommand extends Command
{
    public function __construct(private readonly MaintenanceContractSyncService $syncService, private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = fopen($this->projectDir . '/var/cardnext_erp_maintenance_contract_sync.lock', 'c');
        if ($lock === false) {
            $output->writeln('<error>The synchronization lock could not be opened.</error>');

            return Command::FAILURE;
        }
        if (!flock($lock, \LOCK_EX | \LOCK_NB)) {
            $output->writeln('<comment>A maintenance-contract synchronization is already running.</comment>');
            fclose($lock);

            return Command::SUCCESS;
        }

        try {
            $r = $this->syncService->synchronize();
            $output->writeln(sprintf('Fetched: %d, Created: %d, Updated: %d, Unchanged: %d, Skipped: %d, Errors: %d', $r->fetched, $r->created, $r->updated, $r->unchanged, $r->skipped, $r->errors));

            return $r->errors === 0 ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $error) {
            $output->writeln('<error>Synchronization failed: ' . $error::class . '</error>');

            return Command::FAILURE;
        } finally {
            flock($lock, \LOCK_UN);
            fclose($lock);
        }
    }
}
