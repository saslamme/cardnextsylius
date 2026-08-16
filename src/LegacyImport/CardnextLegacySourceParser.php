<?php

declare(strict_types=1);

namespace App\LegacyImport;

final readonly class CardnextLegacySourceParser
{
    public function __construct(private LegacyPriceParser $prices, private LegacyCategoryMapper $categories)
    {
    }

    public function parse(string $zipPath): LegacyImportPlan
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException(sprintf('ZIP "%s" is not readable.', $zipPath));
        }
        if ($zip->locateName('products/shop.artindex') === false || $zip->locateName('products/shop.vendor') === false) {
            throw new \RuntimeException('Central source files shop.artindex/shop.vendor are missing.');
        }
        $raw = []; $ignored = 0; $relevantFiles = 0;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_contains($name, '__MACOSX/') || str_contains($name, '/._') || str_ends_with($name, '.DS_Store')) {
                ++$ignored; continue;
            }
            if (!str_ends_with($name, '.dat')) { continue; }
            ++$relevantFiles;
            $content = $zip->getFromIndex($i);
            if ($content === false) { throw new \RuntimeException("Cannot read $name."); }
            foreach (preg_split('/\R/', $content) ?: [] as $line) {
                if (trim($line) === '') { continue; }
                $fields = explode('|', $this->utf8($line));
                if (count($fields) !== 62) { throw new \RuntimeException(sprintf('%s has an unsupported field count.', $name)); }
                $raw[] = $this->record($name, $fields);
            }
        }
        $zipFiles = $zip->numFiles; $zip->close();

        return $this->deduplicate($raw, $zipFiles, $relevantFiles, $ignored);
    }

    /** @param list<string> $f */
    private function record(string $file, array $f): LegacyProductRecord
    {
        $attributes = [];
        foreach (explode(':#', $f[25]) as $item) {
            $parts = explode(':', $item);
            if (count($parts) >= 2 && trim($parts[0]) !== '') { $attributes[trim($parts[0])] = trim($parts[1]); }
        }
        $gtin = preg_replace('/\D/', '', $f[35]) ?: null;
        $imageReference = trim($f[41]);

        return new LegacyProductRecord(trim($f[0]), basename($file), trim($f[2] ?: $f[17]), trim($f[3]), trim($f[4]), $this->prices->parse($f[5]), $this->sanitizeHtml($f[6]), $gtin, $this->categories->map(basename($file)), $attributes, array_values(array_filter(array_map('trim', explode(',', $f[9])))), str_contains(mb_strtolower($f[22]), 'archiv'), $imageReference !== '', array_values($f));
    }

    /** @param list<LegacyProductRecord> $raw */
    private function deduplicate(array $raw, int $zipFiles, int $sourceFiles, int $ignored): LegacyImportPlan
    {
        $byKey = []; $conflicts = [];
        foreach ($raw as $record) {
            $mpn = self::normalize($record->manufacturerPartNumber);
            $key = $mpn !== '' ? 'mpn:'.$mpn : ($record->gtin ? 'gtin:'.$record->gtin : 'legacy:'.$record->legacyId);
            if (!isset($byKey[$key])) { $byKey[$key] = $record; continue; }
            $old = $byKey[$key];
            if (self::normalize($old->manufacturer) !== self::normalize($record->manufacturer) || ($old->price !== null && $record->price !== null && $old->price !== $record->price)) { $conflicts[] = $key; }
            $byKey[$key] = new LegacyProductRecord($old->legacyId, $old->sourceFile, $old->manufacturer ?: $record->manufacturer, $old->manufacturerPartNumber ?: $record->manufacturerPartNumber, strlen($old->name) >= strlen($record->name) ? $old->name : $record->name, $old->price ?? $record->price, strlen($old->description) >= strlen($record->description) ? $old->description : $record->description, $old->gtin ?? $record->gtin, array_values(array_unique([...$old->taxonCodes, ...$record->taxonCodes])), $old->attributes + $record->attributes, array_values(array_unique([...$old->compatibilityReferences, ...$record->compatibilityReferences])), $old->archived && $record->archived, $old->hasImage || $record->hasImage, $old->rawData);
        }
        $records = array_values($byKey); $manufacturers = [];
        foreach ($records as $r) { if ($r->manufacturer !== '') $manufacturers[self::normalize($r->manufacturer)] = $r->manufacturer; }
        $missing = fn (callable $test): int => count(array_filter($records, $test));
        $taxons = array_sum(array_map(fn ($r) => count($r->taxonCodes), $records));
        $needsReview = $missing(fn ($r) => $r->manufacturerPartNumber === '' || $r->taxonCodes === [] || in_array('mpn:'.self::normalize($r->manufacturerPartNumber), $conflicts, true));
        $report = ['zip_files_total'=>$zipFiles,'relevant_source_files'=>$sourceFiles,'ignored_macos_files'=>$ignored,'source_records_total'=>count($raw),'processed_legacy_records'=>count($raw),'deduplicated_records'=>count($raw)-count($records),'unique_skus'=>count($records),'products'=>count($records),'product_variants'=>count($records),'manufacturers'=>count($manufacturers),'taxon_assignments'=>$taxons,'attribute_values'=>array_sum(array_map(fn($r)=>count($r->attributes),$records)),'device_models'=>0,'compatibilities'=>0,'normal_prices'=>$missing(fn($r)=>$r->price!==null),'tier_prices'=>0,'products_imported'=>count($records)-$needsReview,'products_needs_review'=>$needsReview,'missing_mpn'=>$missing(fn($r)=>$r->manufacturerPartNumber===''),'missing_gtin'=>$missing(fn($r)=>$r->gtin===null),'missing_image'=>$missing(fn($r)=>!$r->hasImage),'missing_price'=>$missing(fn($r)=>$r->price===null),'unknown_manufacturers'=>$missing(fn($r)=>$r->manufacturer===''),'unknown_categories'=>$missing(fn($r)=>$r->taxonCodes===[]),'unknown_attributes'=>0,'unresolved_device_compatibilities'=>0,'conflicts'=>count(array_unique($conflicts))];

        return new LegacyImportPlan($records, $report, array_values(array_unique($conflicts)));
    }

    public static function normalize(string $value): string { return mb_strtoupper(preg_replace('/[^A-Z0-9]+/i', '', trim($value)) ?? ''); }
    private function utf8(string $value): string { return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252'); }
    private function sanitizeHtml(string $html): string { $html = preg_replace('#<(script|iframe)\b[^>]*>.*?</\1>#is', '', $html) ?? ''; return preg_replace('/\s(on\w+|style)=("[^"]*"|\'[^\']*\')/i', '', $html) ?? ''; }
}
