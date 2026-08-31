<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class QuoteMigrationTest extends TestCase
{
    public function testEditableQuoteTablesHaveVersioningIndexesAndForeignKeys(): void
    {
        $migration = file_get_contents(\dirname(__DIR__, 2) . '/migrations/Version20260829140000.php');
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

    public function testCustomerAccountMigrationHandlesFreshAndPartiallyMigratedSchemas(): void
    {
        require_once \dirname(__DIR__, 2) . '/migrations/Version20260830190000.php';

        $schema = new Schema();
        $freshTable = $schema->createTable('cardnext_quote');
        $freshTable->addColumn('access_token_hash', 'string');
        $freshTable->addColumn('access_token_issued_at', 'datetime_immutable');

        self::assertFalse($freshTable->hasColumn('customer_id'));
        self::assertTrue($freshTable->hasColumn('access_token_hash'));
        self::assertTrue($freshTable->hasColumn('access_token_issued_at'));
        self::assertFalse($this->migrationDecision('hasCustomerForeignKey', $freshTable));
        self::assertFalse($this->migrationDecision('hasCustomerIndex', $freshTable));

        $partialTable = $schema->createTable('partially_migrated_cardnext_quote');
        $partialTable->addColumn('customer_id', 'integer');
        $partialTable->addColumn('customer_email', 'string');
        $partialTable->addIndex(['customer_id', 'customer_email'], 'existing_customer_lookup');
        $partialTable->addForeignKeyConstraint(
            'sylius_customer',
            ['customer_id'],
            ['id'],
            ['onDelete' => 'SET NULL'],
            'an_existing_constraint_name',
        );

        self::assertTrue($partialTable->hasColumn('customer_id'));
        self::assertTrue($this->migrationDecision('hasCustomerForeignKey', $partialTable));
        self::assertTrue($this->migrationDecision('hasCustomerIndex', $partialTable));
    }

    public function testCustomerAccountMigrationMatchesForeignKeysByRelationshipNotName(): void
    {
        require_once \dirname(__DIR__, 2) . '/migrations/Version20260830190000.php';

        $schema = new Schema();
        $table = $schema->createTable('cardnext_quote');
        $table->addColumn('customer_id', 'integer');
        $table->addColumn('other_id', 'integer');
        $table->addIndex(['other_id', 'customer_id'], 'customer_id_is_not_leading');
        self::assertFalse($this->migrationDecision('hasCustomerIndex', $table));

        $table->addForeignKeyConstraint(
            'another_table',
            ['customer_id'],
            ['id'],
            [],
            'FK_CARDNEXT_QUOTE_CUSTOMER_ACCOUNT',
        );

        self::assertFalse($this->migrationDecision('hasCustomerForeignKey', $table));
    }

    private function migrationDecision(string $method, object $table): bool
    {
        require_once \dirname(__DIR__, 2) . '/migrations/Version20260830190000.php';
        $migrationClass = 'DoctrineMigrations\\Version20260830190000';
        self::assertTrue(class_exists($migrationClass));
        $decision = new ReflectionMethod($migrationClass, $method);
        $result = $decision->invoke(null, $table);
        self::assertIsBool($result);

        return $result;
    }
}
