<?php
declare(strict_types=1);
namespace App\Tests\Cms;
use App\Cms\CmsSlug; use PHPUnit\Framework\Attributes\DataProvider; use PHPUnit\Framework\TestCase;
final class CmsSlugTest extends TestCase { public function testNormalizesNestedSlug():void{self::assertSame('service/support',CmsSlug::normalize('/Service//Support/'));} #[DataProvider('unsafe')] public function testRejectsUnsafeAndReservedPaths(string $slug):void{self::assertFalse(CmsSlug::isSafe($slug));} public static function unsafe():iterable{return [[''],['admin/users'],['api'],['checkout/complete'],['../secret'],['support%2Fadmin']];} public function testAcceptsNestedLocalizedSlug():void{self::assertTrue(CmsSlug::isSafe('unternehmen/ueber-uns'));} }
