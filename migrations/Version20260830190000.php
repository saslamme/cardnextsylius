<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move quote access to authenticated Sylius customer accounts';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('cardnext_quote');

        if (!$table->hasColumn('customer_id')) {
            $this->addSql('ALTER TABLE cardnext_quote ADD customer_id INT DEFAULT NULL');
        }

        // Safe to repeat after a partially completed migration: already linked quotes keep the same customer.
        $this->addSql("UPDATE cardnext_quote q INNER JOIN (SELECT LOWER(TRIM(email)) email_key, MIN(id) id FROM sylius_customer WHERE email IS NOT NULL AND TRIM(email) <> '' GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) = 1) c ON c.email_key = LOWER(TRIM(q.customer_email)) SET q.customer_id = c.id");

        if (!self::hasCustomerForeignKey($table)) {
            $this->addSql('ALTER TABLE cardnext_quote ADD CONSTRAINT FK_CARDNEXT_QUOTE_CUSTOMER_ACCOUNT FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE SET NULL');
        }

        if (!self::hasCustomerIndex($table)) {
            $this->addSql('CREATE INDEX IDX_CN_OFFER_CUSTOMER ON cardnext_quote (customer_id)');
        }

        if ($table->hasColumn('access_token_hash')) {
            $this->addSql('ALTER TABLE cardnext_quote DROP access_token_hash');
        }

        if ($table->hasColumn('access_token_issued_at')) {
            $this->addSql('ALTER TABLE cardnext_quote DROP access_token_issued_at');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_quote ADD access_token_hash VARCHAR(64) DEFAULT NULL, ADD access_token_issued_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cardnext_quote DROP FOREIGN KEY FK_CARDNEXT_QUOTE_CUSTOMER_ACCOUNT');
        $this->addSql('DROP INDEX IDX_CN_OFFER_CUSTOMER ON cardnext_quote');
        $this->addSql('ALTER TABLE cardnext_quote DROP customer_id');
    }

    private static function hasCustomerForeignKey(Table $table): bool
    {
        foreach ($table->getForeignKeys() as $foreignKey) {
            if (self::isCustomerForeignKey($foreignKey)) {
                return true;
            }
        }

        return false;
    }

    private static function isCustomerForeignKey(ForeignKeyConstraint $foreignKey): bool
    {
        return self::normalizedNames($foreignKey->getLocalColumns()) === ['customer_id']
            && strtolower($foreignKey->getForeignTableName()) === 'sylius_customer'
            && self::normalizedNames($foreignKey->getForeignColumns()) === ['id'];
    }

    private static function hasCustomerIndex(Table $table): bool
    {
        foreach ($table->getIndexes() as $index) {
            if (self::isCustomerIndex($index)) {
                return true;
            }
        }

        return false;
    }

    private static function isCustomerIndex(Index $index): bool
    {
        $columns = self::normalizedNames($index->getColumns());

        return ($columns[0] ?? null) === 'customer_id';
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function normalizedNames(array $names): array
    {
        return array_map(static fn (string $name): string => strtolower(trim($name, '`')), $names);
    }
}
