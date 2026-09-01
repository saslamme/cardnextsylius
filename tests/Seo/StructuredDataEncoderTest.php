<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Seo\StructuredData\StructuredDataEncoder;
use PHPUnit\Framework\TestCase;

final class StructuredDataEncoderTest extends TestCase
{
    public function testItProducesParseableScriptSafeJsonWithoutLosingUnicode(): void
    {
        $encoded = (new StructuredDataEncoder())->encode(['name' => '</script> "Käse" & mehr']);

        self::assertStringNotContainsString('</script>', $encoded);
        self::assertStringNotContainsString('&', $encoded);
        self::assertStringContainsString('Käse', $encoded);
        self::assertSame(['name' => '</script> "Käse" & mehr'], json_decode($encoded, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testItOmitsAbsentGraphs(): void
    {
        self::assertSame('', (new StructuredDataEncoder())->encode(null));
    }
}
