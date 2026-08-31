<?php

declare(strict_types=1);

namespace App\LegacyImport;

use App\Service\CardnextProductCsvImporter;
use Symfony\Component\String\Slugger\AsciiSlugger;

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
            'generated_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'source' => basename($zip),
            'dry_run' => $dryRun,
            'statistics' => $report,
        ];
        if (file_put_contents($reportPath, json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n", \LOCK_EX) === false) {
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

        $header = ['product_code', 'variant_code', 'locale', 'name', 'model', 'taxon_code', 'channel_codes', 'prices_json', 'manufacturer_name', 'manufacturer_code', 'manufacturer_part_number', 'gtin', 'short_description', 'description', 'attributes_json', 'compatibilities_json', 'device_compatibilities_json', 'images', 'documents_json', 'data_quality_status', 'enabled', 'variant_enabled', 'minimum_order_quantity', 'order_increment', 'pack_quantity', 'slug'];
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

        // Sylius requires a locale+slug combination to be unique. Legacy data
        // may contain different sellable SKUs with exactly the same title. Keep
        // the short title-based slug when it is unique; for duplicate titles,
        // append a deterministic manufacturer/MPN suffix to every member of the
        // collision group so reruns are stable and independent of row order.
        $productSlugs = $this->buildProductSlugs($plan);

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
            $manufacturerCode = $r->manufacturer !== '' ? 'LEGACY_MFR_' . CardnextLegacySourceParser::normalize($r->manufacturer) : '';

            foreach ($r->taxonCodes as $taxon) {
                $row = [$code, $code, 'de_DE', $r->name, $r->model, $taxon, 'CARDNEXT_DE', json_encode($r->price === null ? [] : ['CARDNEXT_DE' => $r->price], \JSON_THROW_ON_ERROR), $r->manufacturer, $manufacturerCode, $r->manufacturerPartNumber, $r->gtin ?? '', '', $r->description, json_encode($r->attributes, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE), json_encode($relations, \JSON_THROW_ON_ERROR), '[]', '', '[]', $quality, $r->archived ? '0' : '1', $r->archived ? '0' : '1', '1', '1', '1', $productSlugs[$code]];
                fputcsv($h, $row, ';');
            }
        }

        fclose($h);
    }

    /** @return array<string,string> product code => slug */
    private function buildProductSlugs(LegacyImportPlan $plan): array
    {
        $slugger = new AsciiSlugger();
        $recordsByCode = [];
        $groups = [];

        foreach ($plan->records as $record) {
            if ($record->taxonCodes === []) {
                continue;
            }

            $code = CardnextLegacySourceParser::productCode($record);
            if (isset($recordsByCode[$code])) {
                continue;
            }

            $source = $record->name !== '' ? $record->name : $code;
            $base = strtolower((string) $slugger->slug($source));
            if ($base === '') {
                $base = strtolower((string) $slugger->slug($code));
            }

            $recordsByCode[$code] = ['record' => $record, 'base' => $base];
            $groups[$base][] = $code;
        }

        $slugs = [];
        $used = [];

        // Reserve all naturally unique title slugs first.
        foreach ($groups as $base => $codes) {
            $codes = array_values(array_unique($codes));
            if (count($codes) !== 1) {
                continue;
            }

            $code = $codes[0];
            $candidate = substr($base, 0, 255);
            $slugs[$code] = $candidate;
            $used[$candidate] = $code;
        }

        // Resolve duplicate title slugs deterministically using manufacturer+MPN.
        foreach ($groups as $base => $codes) {
            $codes = array_values(array_unique($codes));
            if (count($codes) < 2) {
                continue;
            }

            sort($codes, \SORT_STRING);
            foreach ($codes as $code) {
                /** @var LegacyProductRecord $record */
                $record = $recordsByCode[$code]['record'];
                $suffixSource = trim($record->manufacturer . ' ' . $record->manufacturerPartNumber);
                if ($suffixSource === '') {
                    $suffixSource = $code;
                }

                $suffix = strtolower((string) $slugger->slug($suffixSource));
                $candidate = $this->combineSlug($base, $suffix);

                if (isset($used[$candidate]) && $used[$candidate] !== $code) {
                    $codeSuffix = strtolower((string) $slugger->slug($code));
                    $candidate = $this->combineSlug($base, $codeSuffix);
                }

                if (isset($used[$candidate]) && $used[$candidate] !== $code) {
                    $candidate = $this->combineSlug($base, substr(sha1($code), 0, 10));
                }

                $slugs[$code] = $candidate;
                $used[$candidate] = $code;
            }
        }

        return $slugs;
    }

    private function combineSlug(string $base, string $suffix): string
    {
        $suffix = trim($suffix, '-');
        if ($suffix === '') {
            return substr($base, 0, 255);
        }

        $maxBaseLength = max(1, 255 - strlen($suffix) - 1);
        $trimmedBase = rtrim(substr($base, 0, $maxBaseLength), '-');

        return $trimmedBase . '-' . $suffix;
    }
}
