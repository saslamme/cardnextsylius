<?php

declare(strict_types=1);

namespace App\LegacyImport;

use App\Service\CardnextProductCsvImporter;

final readonly class CardnextLegacyProductImporter
{
    public function __construct(private CardnextLegacySourceParser $parser, private CardnextProductCsvImporter $csvImporter)
    {
    }

    /** @return array<string,mixed> */
    public function import(string $zip, bool $dryRun, string $reportPath): array
    {
        $plan = $this->parser->parse($zip);
        if (!$dryRun) {
            $csv = tempnam(sys_get_temp_dir(), 'cardnext-legacy-');
            if ($csv === false) { throw new \RuntimeException('Could not create the temporary import file.'); }
            try {
                $this->writeCsv($csv, $plan);
                $this->csvImporter->import($csv, false);
            } finally { @unlink($csv); }
        }
        $directory = dirname($reportPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) { throw new \RuntimeException("Cannot create report directory $directory."); }
        $payload = ['generated_at'=>(new \DateTimeImmutable())->format(DATE_ATOM),'source'=>basename($zip),'dry_run'=>$dryRun,'statistics'=>$plan->report];
        if (file_put_contents($reportPath, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n", LOCK_EX) === false) { throw new \RuntimeException("Cannot write report $reportPath."); }

        return $plan->report;
    }

    private function writeCsv(string $path, LegacyImportPlan $plan): void
    {
        $h = fopen($path, 'wb'); if ($h === false) throw new \RuntimeException('Cannot open temporary CSV.');
        $header = ['product_code','variant_code','locale','name','taxon_code','channel_codes','prices_json','manufacturer_name','manufacturer_code','manufacturer_part_number','gtin','description','data_quality_status','enabled','variant_enabled','minimum_order_quantity','order_increment','pack_quantity'];
        fputcsv($h, $header, ';');
        foreach ($plan->records as $r) {
            if ($r->taxonCodes === []) { continue; }
            $stable = CardnextLegacySourceParser::normalize($r->manufacturerPartNumber ?: $r->legacyId);
            $reviewKey = 'mpn:'.CardnextLegacySourceParser::normalize($r->manufacturerPartNumber);
            $quality = $r->manufacturerPartNumber === '' || $r->manufacturer === '' || in_array($reviewKey, $plan->reviewKeys, true) ? 'needs_review' : 'imported';
            foreach ($r->taxonCodes as $taxon) {
                $row = ['LEGACY_'.$stable,'LEGACY_'.$stable,'de_DE',$r->name,$taxon,'CARDNEXT_DE',json_encode($r->price === null ? [] : ['CARDNEXT_DE'=>$r->price]),$r->manufacturer,'LEGACY_MFR_'.CardnextLegacySourceParser::normalize($r->manufacturer),$r->manufacturerPartNumber,$r->gtin ?? '',$r->description,$quality,$r->archived?'0':'1',$r->archived?'0':'1','1','1','1'];
                fputcsv($h, $row, ';');
            }
        }
        fclose($h);
    }
}
