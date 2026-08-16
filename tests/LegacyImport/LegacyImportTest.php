<?php

declare(strict_types=1);

namespace App\Tests\LegacyImport;

use App\LegacyImport\CardnextLegacySourceParser;
use App\LegacyImport\CardnextLegacyProductImporter;
use App\LegacyImport\LegacyCategoryMapper;
use App\LegacyImport\LegacyImportPlan;
use App\LegacyImport\LegacyPriceParser;
use App\LegacyImport\LegacyProductRecord;
use App\Service\CardnextProductCsvImporter;
use PHPUnit\Framework\TestCase;

final class LegacyImportTest extends TestCase
{
    public function testGermanAndInternationalPricesAreExact(): void
    {
        $p = new LegacyPriceParser();
        self::assertSame(123495, $p->parse('1.234,95 EUR'));
        self::assertSame(123495, $p->parse('1234.95'));
        self::assertSame(1295, $p->parse('12,95'));
    }

    public function testCategoryCanMapToMultipleTaxons(): void
    {
        self::assertSame(['plastic_cards_rfid','rfid_transponder_cards'], (new LegacyCategoryMapper())->map('RFID-TransponderRFID-Karten_Mifare.dat'));
    }

    public function testRealArchiveIsParsedAndDeduplicated(): void
    {
        $zip = dirname(__DIR__, 2).'/import-source/products.zip';
        if (!is_file($zip)) self::markTestSkipped('Private migration archive is not present.');
        $plan = (new CardnextLegacySourceParser(new LegacyPriceParser(), new LegacyCategoryMapper()))->parse($zip);
        self::assertSame(4311, $plan->report['source_records_total']);
        self::assertLessThan(4311, $plan->report['unique_skus']);
        self::assertSame(35, $plan->report['manufacturers']);
        self::assertSame(35, $plan->report['vendor_vocabulary_entries']);
        self::assertSame(1442, $plan->report['artindex_entries']);
        self::assertSame(1, $plan->report['real_conflicts']);
    }

    public function testRequiredLegacyCategoryMappings(): void
    {
        $mapper = new LegacyCategoryMapper();
        self::assertSame(['id_accessories_sets'], $mapper->map('AusweiszubehoerAusweissets.dat'));
        self::assertSame(['id_accessories_punches'], $mapper->map('AusweiszubehoerKartenlocher.dat'));
        self::assertSame(['id_accessories_rfid_protection'], $mapper->map('AusweiszubehoerRFID-Schutz.dat'));
        self::assertSame(['plastic_cards_signature'], $mapper->map('VerbrauchsmaterialBlankokarten_Unterschriftenfeld.dat'));
        self::assertSame(['plastic_cards_other'], $mapper->map('VerbrauchsmaterialBlankokarten_Sonstige.dat'));
    }

    public function testField17VocabulariesArtIndexAndManufacturerScopedDeduplication(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'legacy-test-');
        self::assertNotFalse($zipPath);
        $zip = new \ZipArchive(); self::assertTrue($zip->open($zipPath, \ZipArchive::OVERWRITE) === true);
        $zip->addFromString('products/shop.vendor', "OEM\nMagicard\n");
        $zip->addFromString('products/shop.attrib', "Modell|Pronto|\nDruckauflösung|300 dpi|\n");
        $zip->addFromString('products/shop.artindex', "1|ABC-1|\n2|ABC-1|\n3|INDEXED-3|\n");
        $zip->addFromString('products/Kartendrucker.dat', implode("\n", [
            $this->row('1', 'Kategorie, nicht Hersteller', 'ABC-1', 'OEM', 'Modell:Pronto:1::#Druckauflösung:300 dpi:1:'),
            $this->row('2', 'Andere Kategorie', 'ABC1', 'Magicard', ''),
            $this->row('3', 'Kartenjojos', 'DAT-3', 'OEM', ''),
        ]));
        $zip->close();
        try { $plan = (new CardnextLegacySourceParser(new LegacyPriceParser(), new LegacyCategoryMapper()))->parse($zipPath); }
        finally { @unlink($zipPath); }
        self::assertCount(3, $plan->records, 'The same MPN from different manufacturers must not merge.');
        $manufacturers=array_values(array_unique(array_map(fn($r) => $r->manufacturer, $plan->records))); sort($manufacturers);
        self::assertSame(['Magicard','OEM'], $manufacturers);
        self::assertNotContains('Kategorie, nicht Hersteller', array_map(fn($r) => $r->manufacturer, $plan->records));
        self::assertSame(3, $plan->report['artindex_entries']);
        self::assertSame(2, $plan->report['vendor_vocabulary_entries']);
        self::assertContains('artindex_mpn_mismatch', $plan->records[2]->reviewReasons);
        self::assertSame('Pronto', $plan->records[0]->model);
        self::assertSame('dpi_300', $plan->records[0]->attributes['CN_PRINT_RESOLUTION']);
    }

    public function testCsvContainsAttributesRelationsAndAllTaxonsWithoutVariantDuplication(): void
    {
        $record = new LegacyProductRecord('1','x.dat','OEM','ABC-1','Name',1200,'Description',null,['one','two'],['CN_PRINT_RESOLUTION'=>'dpi_300'],[],false,false,[], 'Model', [], ['LEGACY_OEM_TARGET']);
        $csv = tempnam(sys_get_temp_dir(), 'legacy-csv-'); self::assertNotFalse($csv);
        $dependency = (new \ReflectionClass(CardnextProductCsvImporter::class))->newInstanceWithoutConstructor();
        (new CardnextLegacyProductImporter((new \ReflectionClass(CardnextLegacySourceParser::class))->newInstanceWithoutConstructor(), $dependency))->writeCsv($csv, new LegacyImportPlan([$record], []));
        $h=fopen($csv,'rb'); self::assertIsResource($h); $header=fgetcsv($h,0,';'); $a=fgetcsv($h,0,';'); $b=fgetcsv($h,0,';'); fclose($h); @unlink($csv);
        self::assertSame('attributes_json', $header[14]);
        self::assertSame($a[0], $b[0]); self::assertSame($a[1], $b[1]);
        self::assertSame(['one','two'], [$a[5],$b[5]]);
        self::assertSame(['CN_PRINT_RESOLUTION'=>'dpi_300'], json_decode($a[14],true));
        self::assertSame('LEGACY_OEM_TARGET', json_decode($a[15],true)[0]['target_code']);
    }

    public function testCsvLeavesManufacturerFieldsEmptyWhenManufacturerIsMissing(): void
    {
        $record = new LegacyProductRecord('1','x.dat','','ABC-1','Name',1200,'Description',null,['one'],[],[],false,false,[], '', ['missing_manufacturer']);
        $csv = tempnam(sys_get_temp_dir(), 'legacy-csv-'); self::assertNotFalse($csv);
        $dependency = (new \ReflectionClass(CardnextProductCsvImporter::class))->newInstanceWithoutConstructor();
        (new CardnextLegacyProductImporter((new \ReflectionClass(CardnextLegacySourceParser::class))->newInstanceWithoutConstructor(), $dependency))->writeCsv($csv, new LegacyImportPlan([$record], []));
        $h=fopen($csv,'rb'); self::assertIsResource($h); fgetcsv($h,0,';'); $row=fgetcsv($h,0,';'); fclose($h); @unlink($csv);
        self::assertSame('', $row[8]);
        self::assertSame('', $row[9]);
        self::assertSame('needs_review', $row[19]);
    }

    private function row(string $id,string $category,string $mpn,string $manufacturer,string $attributes): string
    {
        $f=array_fill(0,62,''); $f[0]=$id; $f[2]=$category; $f[3]=$mpn; $f[4]='Test'; $f[5]='12,00'; $f[17]=$manufacturer; $f[25]=$attributes;
        return implode('|',$f);
    }
}
