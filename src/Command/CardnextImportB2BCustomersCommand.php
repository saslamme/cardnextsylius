<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\B2BCustomerCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:import-b2b-customers',
    description: 'Imports Cardnext B2B profile data for existing Sylius customers.',
)]
final class CardnextImportB2BCustomersCommand extends Command
{
    public function __construct(
        private readonly B2BCustomerCsvImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv', InputArgument::REQUIRED, 'Path to semicolon-separated CSV file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate without committing database changes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csv = (string) $input->getArgument('csv');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $result = $this->importer->import($csv, $dryRun);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->table(
            ['Kennzahl', 'Wert'],
            [
                ['Zeilen', $result['rows']],
                ['B2B-Profile neu', $result['created']],
                ['B2B-Profile aktualisiert', $result['updated']],
                ['Unverändert', $result['unchanged']],
            ],
        );

        if ($result['warnings'] !== []) {
            $io->warning($result['warnings']);
        }

        $io->success($dryRun ? 'Dry-Run erfolgreich.' : 'B2B-Kundenimport abgeschlossen.');

        return Command::SUCCESS;
    }
}
