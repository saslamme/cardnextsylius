<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Service\Search\SearchSynonymParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchSynonymParserTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('synonyms')]
    public function testItSplitsTrimsAndCaseInsensitivelyDeduplicates(?string $input, array $expected): void
    {
        self::assertSame($expected, (new SearchSynonymParser())->parse($input));
    }

    /**
     * @return iterable<string, array{?string, list<string>}>
     */
    public static function synonyms(): iterable
    {
        yield 'all separators and phrases' => [
            "Ausweisdrucker\nPlastic Card Printer, Badge Printer; ID Card Printer",
            ['Ausweisdrucker', 'Plastic Card Printer', 'Badge Printer', 'ID Card Printer'],
        ];
        yield 'case insensitive duplicates' => [
            'Badge Printer, badge printer; BADGE PRINTER',
            ['Badge Printer'],
        ];
        yield 'empty' => [" \n,; ", []];
        yield 'null' => [null, []];
    }
}
