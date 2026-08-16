<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\OpenAiProductAttributeEnricher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cardnext:enrich-product-attributes',
    description: 'Fills empty Cardnext product attributes automatically using OpenAI.',
)]
final class CardnextEnrichProductAttributesCommand extends Command
{
    public function __construct(private readonly OpenAiProductAttributeEnricher $enricher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyze only; do not write product attributes.')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Allow replacing already populated attribute values.')
            ->addOption('product', null, InputOption::VALUE_REQUIRED, 'Process only one Sylius product code.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of products to scan.')
            ->addOption('min-confidence', null, InputOption::VALUE_REQUIRED, 'Minimum confidence required for writing.', '0.90')
            ->addOption('web', null, InputOption::VALUE_NONE, 'Allow OpenAI web search for exact manufacturer/model/MPN facts.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Cardnext Automatic Product Attribute Enrichment');

        $product = $input->getOption('product');
        $limit = $input->getOption('limit');
        $minimumConfidence = $input->getOption('min-confidence');

        try {
            $result = $this->enricher->enrich(
                (bool) $input->getOption('dry-run'),
                (bool) $input->getOption('overwrite'),
                is_string($product) && trim($product) !== '' ? trim($product) : null,
                is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null,
                is_numeric($minimumConfidence) ? (float) $minimumConfidence : 0.90,
                (bool) $input->getOption('web'),
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return self::FAILURE;
        }

        $io->table(['Metric', 'Value'], [
            ['Produkte geprüft', $result['products_scanned']],
            ['API-Aufrufe', $result['api_calls']],
            ['Produkte mit Änderungen', $result['products_changed']],
            ['Leere Attributfelder', $result['candidate_slots']],
            ['Vorschläge erhalten', $result['suggestions_received']],
            ['Würden geschrieben', $result['values_would_write']],
            ['Geschrieben', $result['values_written']],
            ['Unter Konfidenzgrenze', $result['low_confidence_skipped']],
            ['Ungültige Werte', $result['invalid_values_skipped']],
            ['Unbekannte Attribute', $result['unknown_attribute_skipped']],
            ['Ohne leere Attribute', $result['products_without_empty_slots']],
            ['API-Fehler', $result['api_errors']],
        ]);

        if ($result['changes'] !== []) {
            $io->section('Beispieländerungen');
            $io->table(
                ['Produkt', 'Attribut', 'Neu', 'Konfidenz', 'Beleg'],
                array_map(
                    static fn (array $change): array => [
                        $change['product'],
                        $change['attribute'],
                        is_array($change['new']) ? json_encode($change['new'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $change['new'],
                        number_format((float) $change['confidence'], 2, ',', '.'),
                        mb_strimwidth((string) $change['evidence'], 0, 90, '…'),
                    ],
                    $result['changes'],
                ),
            );
        }

        if ($result['errors'] !== []) {
            $io->warning($result['errors']);
        }

        if ((bool) $input->getOption('dry-run')) {
            $io->success('Dry-Run abgeschlossen. Es wurden keine Attributwerte gespeichert. API-Aufrufe können dennoch Kosten verursachen.');
        } else {
            $io->success('Automatische Attributbefüllung abgeschlossen. Bereits gefüllte Werte wurden ohne --overwrite nicht verändert.');
        }

        return self::SUCCESS;
    }
}
