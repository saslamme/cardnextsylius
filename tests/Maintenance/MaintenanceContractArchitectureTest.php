<?php
declare(strict_types=1);
namespace App\Tests\Maintenance;
use PHPUnit\Framework\TestCase;
final class MaintenanceContractArchitectureTest extends TestCase
{
    public function testAccountDoesNotExposeInternalNoteOrCustomerRequestIdentity(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../src/Controller/Shop/MaintenanceContractAccountController.php');
        $template = file_get_contents(__DIR__ . '/../../templates/shop/account/maintenance_contract/index.html.twig');
        self::assertIsString($controller); self::assertIsString($template);
        self::assertStringNotContainsString('internalNote', $template);
        self::assertStringNotContainsString('HttpClient', $controller);
        self::assertStringNotContainsString('Request $request', $controller);
        self::assertStringContainsString("methods: ['GET']", $controller);
        self::assertStringContainsString("private, no-store", $controller);
        self::assertStringContainsString('noindex, nofollow, noarchive', $controller);
    }
    public function testAdminHasNoCreateRouteAndSyncIsPostWithCsrf(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../src/Controller/Admin/MaintenanceContractAdminController.php');
        $template = file_get_contents(__DIR__ . '/../../templates/admin/cardnext/maintenance_contract/index.html.twig');
        self::assertIsString($controller);
        self::assertIsString($template);
        self::assertStringNotContainsString('function create(', $controller);
        self::assertStringContainsString("methods: ['POST']", $controller);
        self::assertStringContainsString("isCsrfTokenValid('maintenance-contract-sync'", $controller);
        self::assertStringContainsString("isCsrfTokenValid('maintenance-contract-note-'", $controller);
        self::assertStringContainsString('DISTINCT contract', $controller);
        self::assertStringContainsString("leftJoin('contract.devices', 'devices')", $controller);
        self::assertStringContainsString('devices.serialNumber', $controller);
        self::assertStringContainsString('deviceCount', $template);
    }
}
