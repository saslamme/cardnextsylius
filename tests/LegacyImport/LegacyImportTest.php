<?php

declare(strict_types=1);

namespace App\Tests\LegacyImport;

use App\LegacyImport\CardnextLegacySourceParser;
use App\LegacyImport\LegacyCategoryMapper;
use App\LegacyImport\LegacyPriceParser;
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
        self::assertGreaterThan(40, $plan->report['manufacturers']);
    }
}
