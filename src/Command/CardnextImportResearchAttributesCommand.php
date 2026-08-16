<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CardnextResearchAttributeCsvImporterFinal;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:import-research-attributes',
    description: 'Imports researched CN_* product attributes from a semicolon CSV matched by manufacturer part number.',
)]
final class CardnextImportResearchAttributesCommand extends Command
{
    public function __construct(private readonly CardnextResearchAttributeCsvImporterFinal $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv', InputArgument::REQUIRED, 'Path to the researched CSV file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and show changes without writing')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite already populated attribute values')
            ->addOption('include-ambiguous', null, InputOption::VALUE_NONE, 'Also import rows with research_status=ambiguous');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csv = $input->getArgument('csv');
        if (!is_string($csv) || trim($csv) === '') {
            $io->error('CSV path is required.');
            return self::INVALID;
        }

        try {
            $result = $this->importer->import(
                trim($csv),
                (bool) $input->getOption('dry-run'),
                (bool) $input->getOption('overwrite'),
                (bool) $input->getOption('include-ambiguous'),
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return self::FAILURE;
        }

        $io->title('Cardnext Research Attribute Import');
        $io->table(['Metric', 'Value'], [
            ['CSV-Zeilen', $result['rows']],
            ['Produkte gefunden', $result['products_found']],
            ['Produkte nicht gefunden', $result['products_missing']],
            ['Mehrdeutige MPN-Treffer', $result['ambiguous_matches']],
            ['Status übersprungen', $result['status_skipped']],
            ['Ohne Attribute', $result['empty_attributes']],
            ['Attributwerte geprüft', $result['candidate_values']],
            ['Würden geschrieben', $result['values_would_write']],
            ['Geschrieben', $result['values_written']],
            ['Bestehende Werte geschützt', $result['existing_values_skipped']],
            ['Attribut-Slots würden angelegt', $result['slots_would_create']],
            ['Attribut-Slots angelegt', $result['slots_created']],
            ['Unbekannte Attribute', $result['unknown_attributes']],
            ['Ungültige Werte', $result['invalid_values']],
            ['Hersteller-Abweichungen', $result['manufacturer_mismatches']],
        ]);

        if ($result['changes'] !== []) {
            $io->section('Beispieländerungen');
            $io->table(
                ['MPN', 'Produkt', 'Attribut', 'Alt', 'Neu'],
                array_map(static fn (array $change): array => [
                    $change['mpn'],
                    $change['product'],
                    $change['attribute'],
                    self::formatValue($change['old']),
                    self::formatValue($change['new']),
                ], $result['changes']),
            );
        }

        if ($result['warnings'] !== []) {
            $io->section('Hinweise');
            foreach ($result['warnings'] as $warning) {
                $io->writeln(' - '.$warning);
            }
        }

        if ((bool) $input->getOption('dry-run')) {
            $io->success('Dry-Run abgeschlossen. Es wurden keine Daten geändert.');
        } else {
            $io->success('Recherche-Attribute importiert. Andere Produktdaten wurden nicht verändert.');
        }

        return self::SUCCESS;
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null) return 'NULL';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        return (string) $value;
    }
}
