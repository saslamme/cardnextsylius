<?php

declare(strict_types=1);

namespace App\Command;

use App\LegacyImport\CardnextLegacyAttributeBackfill;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:import-legacy-attributes',
    description: 'Backfills technical product attributes from the legacy archive without touching prices, variants, texts or taxons.',
)]
final class CardnextImportLegacyAttributesCommand extends Command
{
    public function __construct(private readonly CardnextLegacyAttributeBackfill $backfill)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('zip', InputArgument::REQUIRED, 'Path to products.zip')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing to the database')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite already populated attribute values')
            ->addOption('product', null, InputOption::VALUE_REQUIRED, 'Limit import to one Sylius product code, e.g. LEGACY_DASCOM_DC2300');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $zip = $input->getArgument('zip');
        $product = $input->getOption('product');

        if (!is_string($zip) || $zip === '') {
            $io->error('ZIP path is required.');
            return self::INVALID;
        }

        if ($product !== null && !is_string($product)) {
            $io->error('Product code must be a string.');
            return self::INVALID;
        }

        try {
            $result = $this->backfill->backfill(
                $zip,
                (bool) $input->getOption('dry-run'),
                (bool) $input->getOption('overwrite'),
                $product,
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return self::FAILURE;
        }

        $io->title('Cardnext Legacy Attribute Backfill');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Produkte geprüft', $result['products_scanned']],
                ['Produkte gefunden', $result['products_found']],
                ['Produkte mit Änderungen', $result['products_changed']],
                ['Produkte nicht gefunden', $result['products_missing']],
                ['Erkannte Attributwerte', $result['candidate_values']],
                ['Würden geschrieben', $result['values_would_write']],
                ['Geschrieben', $result['values_written']],
                ['Bestehende Werte übersprungen', $result['existing_values_skipped']],
                ['Nicht im Produktprofil', $result['missing_profile_slots']],
                ['Ungültige Werte übersprungen', $result['invalid_values_skipped']],
                ['Unverändert', $result['unchanged_values']],
            ],
        );

        if ($result['changes'] !== []) {
            $io->section('Beispieländerungen');
            $rows = [];
            foreach ($result['changes'] as $change) {
                $rows[] = [
                    $change['product'],
                    $change['attribute'],
                    $this->formatValue($change['old']),
                    $this->formatValue($change['new']),
                ];
            }
            $io->table(['Produkt', 'Attribut', 'Alt', 'Neu'], $rows);
        }

        if ((bool) $input->getOption('dry-run')) {
            $io->success('Dry-Run abgeschlossen. Es wurden keine Datenbankwerte geändert.');
        } else {
            $io->success('Attribut-Nachimport abgeschlossen. Andere Produktdaten wurden nicht verändert.');
        }

        return self::SUCCESS;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
