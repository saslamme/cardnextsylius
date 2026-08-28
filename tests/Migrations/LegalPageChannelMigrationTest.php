<?php

declare(strict_types=1);

namespace App\Tests\Migrations;

use PHPUnit\Framework\TestCase;

final class LegalPageChannelMigrationTest extends TestCase
{
    public function testMigrationRequiresAndAssignsTheGermanChannelAndIsIrreversible(): void
    {
        $migration = file_get_contents(\dirname(__DIR__, 2) . '/migrations/Version20260828120000.php');

        self::assertIsString($migration);
        self::assertStringContainsString("COUNT(*) FROM sylius_channel WHERE code = 'CARDNEXT_DE'", $migration);
        self::assertStringContainsString('Cannot assign existing legal pages', $migration);
        self::assertStringContainsString('INSERT INTO cardnext_legal_page_channel', $migration);
        self::assertStringContainsString('throw new IrreversibleMigration', $migration);
        self::assertStringNotContainsString('DELETE FROM cardnext_legal_page', $migration);
    }
}
