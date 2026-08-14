<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CardnextProductCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:import-products',
    description: 'Imports or updates Cardnext products and variants from a semicolon-separated CSV file.',
)]
final class CardnextImportProductsCommand extends Command
{
    public function __construct(private readonly CardnextProductCsvImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv', InputArgument::REQUIRED, 'Path to the CSV file')
            ->addOption('images', null, InputOption::VALUE_REQUIRED, 'Directory containing local product images')
            ->addOption('manufacturer-logos', null, InputOption::VALUE_REQUIRED, 'Directory containing local manufacturer logos')
            ->addOption('documents', null, InputOption::VALUE_REQUIRED, 'Directory containing local product PDF documents')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and parse without committing database changes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csv = (string) $input->getArgument('csv');
        $images = $input->getOption('images');
        $manufacturerLogos = $input->getOption('manufacturer-logos');
        $documents = $input->getOption('documents');
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $result = $this->importer->import(
                $csv,
                $dryRun,
                is_string($images) && $images !== '' ? $images : null,
                is_string($manufacturerLogos) && $manufacturerLogos !== '' ? $manufacturerLogos : null,
                is_string($documents) && $documents !== '' ? $documents : null,
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->table(
            ['Kennzahl', 'Wert'],
            [
                ['Zeilen', $result['rows']],
                ['Hersteller neu', $result['manufacturers_created']],
                ['Hersteller aktualisiert', $result['manufacturers_updated']],
                ['Dokumente neu', $result['documents_created']],
                ['Dokumente aktualisiert', $result['documents_updated']],
                ['Kompatibilitäten neu', $result['compatibilities_created']],
                ['Kompatibilitäten aktualisiert', $result['compatibilities_updated']],
                ['Preisregeln neu', $result['price_rules_created']],
                ['Preisregeln aktualisiert', $result['price_rules_updated']],
                ['Kundenpreise neu', $result['customer_price_rules_created']],
                ['Kundenpreise aktualisiert', $result['customer_price_rules_updated']],
                ['Produkte neu', $result['products_created']],
                ['Produkte aktualisiert', $result['products_updated']],
                ['Varianten neu', $result['variants_created']],
                ['Varianten aktualisiert', $result['variants_updated']],
            ],
        );

        foreach ($result['warnings'] as $warning) {
            $io->warning($warning);
        }

        if ($dryRun) {
            $io->success('Dry run erfolgreich. Es wurden keine Daten dauerhaft gespeichert.');
        } else {
            $io->success('Cardnext-Produktimport abgeschlossen.');
        }

        return Command::SUCCESS;
    }
}
