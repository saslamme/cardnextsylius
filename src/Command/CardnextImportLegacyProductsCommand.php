<?php

declare(strict_types=1);

namespace App\Command;

use App\LegacyImport\CardnextLegacyProductImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:cardnext:import-legacy-products', description: 'Imports the complete legacy Cardnext product archive idempotently.')]
final class CardnextImportLegacyProductsCommand extends Command
{
    public function __construct(private readonly CardnextLegacyProductImporter $importer) { parent::__construct(); }
    protected function configure(): void { $this->addArgument('zip', InputArgument::REQUIRED)->addOption('dry-run', null, InputOption::VALUE_NONE)->addOption('report', null, InputOption::VALUE_REQUIRED, 'JSON report path', 'var/import/cardnext-legacy-import-report.json'); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $zip=$input->getArgument('zip'); $report=$input->getOption('report');
        if (!is_string($zip) || !is_string($report)) { $io->error('ZIP and report paths must be strings.'); return self::INVALID; }
        try { $r = $this->importer->import($zip, (bool)$input->getOption('dry-run'), $report); }
        catch (\Throwable $e) { $io->error($e->getMessage()); return self::FAILURE; }
        $io->title('Cardnext Legacy Product Import');
        $io->table(['Metric','Value'], array_map(static fn($k,$v)=>[str_replace('_',' ',ucwords((string)$k,'_')),is_array($v)?json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):$v], array_keys($r), $r));
        $io->success((bool)$input->getOption('dry-run') ? 'Dry run complete; no database writes were performed.' : 'Legacy product import complete.');
        return self::SUCCESS;
    }
}
