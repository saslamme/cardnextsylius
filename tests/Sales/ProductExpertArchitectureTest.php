<?php
declare(strict_types=1);
namespace App\Tests\Sales;
use PHPUnit\Framework\TestCase;
final class ProductExpertArchitectureTest extends TestCase
{
    public function testSalesAccessPrecedesGeneralAdminAccess(): void
    { $yaml = file_get_contents(__DIR__.'/../../config/packages/security.yaml'); self::assertIsString($yaml); self::assertLessThan(strpos($yaml, 'role: ROLE_ADMINISTRATION_ACCESS'), strpos($yaml, 'path: ^/admin/vertrieb')); }
    public function testPublicPageReusesTheCardnextProductCard(): void
    { $twig = file_get_contents(__DIR__.'/../../templates/shop/product_expert/show.html.twig'); self::assertIsString($twig); self::assertStringContainsString("component('cardnext:product:card'", $twig); self::assertStringContainsString('rel="canonical"', $twig); }
}
