<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use App\Branding\HomepageThemeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HomepageThemeResolverTest extends TestCase
{
    #[DataProvider('themes')]
    public function testItOnlyAllowsKnownHomepageThemes(?string $input, string $expected): void
    {
        self::assertSame($expected, HomepageThemeResolver::normalize($input));
    }

    /** @return iterable<string, array{?string, string}> */
    public static function themes(): iterable
    {
        yield 'Cardnext' => ['cardnext', 'cardnext'];
        yield 'Identible' => ['identible', 'identible'];
        yield 'Inplastor' => ['inplastor', 'inplastor'];
        yield 'null fallback' => [null, 'cardnext'];
        yield 'unknown fallback' => ['customer-input', 'cardnext'];
        yield 'normalized case and whitespace' => [' Identible ', 'identible'];
    }
}
