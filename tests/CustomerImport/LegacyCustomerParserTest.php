<?php

declare(strict_types=1);

namespace App\Tests\CustomerImport;

use App\CustomerImport\LegacyCountryMapper;
use App\CustomerImport\LegacyCustomerColumns;
use App\CustomerImport\LegacyCustomerParser;
use PHPUnit\Framework\TestCase;

final class LegacyCustomerParserTest extends TestCase
{
    public function testIso88591IsConvertedWithoutArtifactReplacement(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'legacy-customer-');
        self::assertIsString($path);
        file_put_contents($path, mb_convert_encoding('1|a@example.test|' . str_repeat('a', 32) . '|1||||Müller|Jörg|Groß|Straße 1|12345|Köln|Deutschland' . "\n", 'ISO-8859-1', 'UTF-8'));
        $rows = iterator_to_array((new LegacyCustomerParser())->parse($path, 'ISO-8859-1'));
        @unlink($path);
        self::assertSame('Müller', $rows[0]->get(LegacyCustomerColumns::COMPANY));
        self::assertSame('Straße 1', $rows[0]->get(LegacyCustomerColumns::STREET));
    }

    public function testCountryMappingIsExplicitAndDoesNotGuess(): void
    {
        $mapper = new LegacyCountryMapper();
        self::assertSame('DE', $mapper->map('Deutschland'));
        self::assertSame('AT', $mapper->map('Österreich'));
        self::assertNull($mapper->map('Atlantis'));
    }
}
