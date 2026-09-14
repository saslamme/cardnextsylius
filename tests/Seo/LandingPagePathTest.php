<?php

declare(strict_types=1);

namespace App\Tests\Seo;

use App\Seo\LandingPagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LandingPagePathTest extends TestCase
{
    #[DataProvider('normalizedPaths')]
    public function testNormalizesStablePaths(string $input, string $expected): void { self::assertSame($expected, LandingPagePath::normalize($input)); }
    public static function normalizedPaths(): iterable { yield ['kartendrucker//zebra/', '/kartendrucker/zebra']; yield [' /rfid-leser/elatec/ ', '/rfid-leser/elatec']; }
    #[DataProvider('invalidPaths')]
    public function testRejectsQueryStringsAndFragments(string $input): void { $this->expectException(\InvalidArgumentException::class); LandingPagePath::normalize($input); }
    public static function invalidPaths(): iterable { yield ['/foo?bar=1']; yield ['/foo#bar']; yield ['']; }
}
