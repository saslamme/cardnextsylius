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
        $csv = tempnam(sys_get_temp_dir(), 'cardnext-legacy-');
        if ($csv === false) {
            throw new \RuntimeException('Could not create the temporary import file.');
        }

        try {
            $this->writeCsv($csv, $plan);
            $csvResult = $this->csvImporter->import($csv, $dryRun);
        } finally {
            @unlink($csv);
        }

        $report = $plan->report;
        $report['validated_csv_rows'] = $csvResult['rows'];
        $report['validation_warnings'] = $csvResult['warnings'];

        $directory = dirname($reportPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create report directory $directory.");
        }

        $payload = [
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'source' => basename($zip),
            'dry_run' => $dryRun,
            'statistics' => $report,
        ];
        if (file_put_contents($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write report $reportPath.");
        }

        return $report;
    }

    public function writeCsv(string $path, LegacyImportPlan $plan): void
    {
        $h = fopen($path, 'wb');
        if ($h === false) {
            throw new \RuntimeException('Cannot open temporary CSV.');
        }

        $header = ['product_code','variant_code','locale','name','model','taxon_code','channel_codes','prices_json','manufacturer_name','manufacturer_code','manufacturer_part_number','gtin','short_description','description','attributes_json','compatibilities_json','device_compatibilities_json','images','documents_json','data_quality_status','enabled','variant_enabled','minimum_order_quantity','order_increment','pack_quantity'];
        fputcsv($h, $header, ';');

        // Only products with at least one mapped taxon are emitted to the CSV.
        // Relations must therefore never target records that are deliberately
        // withheld from persistence, otherwise the CSV importer cannot resolve
        // the target product during its second pass.
        $persistedProductCodes = [];
        foreach ($plan->records as $record) {
            if ($record->taxonCodes !== []) {
                $persistedProductCodes[CardnextLegacySourceParser::productCode($record)] = true;
            }
        }

        foreach ($plan->records as $r) {
            if ($r->taxonCodes === []) {
                continue;
            }

            $code = CardnextLegacySourceParser::productCode($r);
            $quality = $r->manufacturerPartNumber === '' || $r->manufacturer === '' || array_diff($r->reviewReasons, ['unresolved_relation']) !== [] ? 'needs_review' : 'imported';
            $relations = array_map(
                static fn (string $target): array => ['target_code' => $target, 'type' => 'compatible_with'],
                array_values(array_filter(
                    $r->relatedProductCodes,
                    static fn (string $target): bool => isset($persistedProductCodes[$target]),
                )),
            );
            $manufacturerCode = $r->manufacturer !== '' ? 'LEGACY_MFR_'.CardnextLegacySourceParser::normalize($r->manufacturer) : '';

            foreach ($r->taxonCodes as $taxon) {
                $row = [$code,$code,'de_DE',$r->name,$r->model,$taxon,'CARDNEXT_DE',json_encode($r->price === null ? [] : ['CARDNEXT_DE'=>$r->price], JSON_THROW_ON_ERROR),$r->manufacturer,$manufacturerCode,$r->manufacturerPartNumber,$r->gtin ?? '','',$r->description,json_encode($r->attributes, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($relations, JSON_THROW_ON_ERROR),'[]','','[]',$quality,$r->archived?'0':'1',$r->archived?'0':'1','1','1','1'];
                fputcsv($h, $row, ';');
            }
        }

        fclose($h);
    }
}
