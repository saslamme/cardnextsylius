<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use PHPUnit\Framework\TestCase;

final class QuoteMigrationTest extends TestCase
{
    public function testEditableQuoteTablesHaveVersioningIndexesAndForeignKeys(): void
    {
        $migration = file_get_contents(\dirname(__DIR__, 2).'/migrations/Version20260829140000.php');
        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE cardnext_quote ', $migration);
        self::assertStringContainsString('CREATE TABLE cardnext_quote_item ', $migration);
        self::assertStringContainsString('UNIQ_CN_OFFER_NUMBER_VERSION', $migration);
        self::assertStringContainsString('FK_CN_OFFER_REQUEST', $migration);
        self::assertStringContainsString('ON DELETE RESTRICT', $migration);
    }

    public function testImmutableDateHotfixOnlyUpdatesTheRequestedDeliveryDateMetadata(): void
    {
        $migration = file_get_contents(\dirname(__DIR__, 2) . '/migrations/Version20260829130000.php');
        self::assertIsString($migration);

        self::assertStringContainsString("COMMENT '(DC2Type:date_immutable)'", $migration);
        self::assertStringNotContainsString('DROP TABLE cardnext_quote_sequence', $migration);
        self::assertStringNotContainsString('quoteRequest_id', $migration);
    }
}
