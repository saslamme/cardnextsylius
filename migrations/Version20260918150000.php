<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add immutable configured items to quote requests and quotes'; }
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.');
        $this->addSql("ALTER TABLE cardnext_quote_request_item ADD item_type VARCHAR(16) DEFAULT 'product' NOT NULL, ADD configured_snapshot JSON DEFAULT NULL, CHANGE product_code product_code VARCHAR(64) DEFAULT NULL, CHANGE variant_code variant_code VARCHAR(64) DEFAULT NULL");
        $this->addSql('ALTER TABLE cardnext_quote_item ADD configured_snapshot JSON DEFAULT NULL, ADD configured_line_total INT DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_quote_item DROP configured_snapshot, DROP configured_line_total');
        $this->addSql("UPDATE cardnext_quote_request_item SET product_code = '' WHERE product_code IS NULL");
        $this->addSql("UPDATE cardnext_quote_request_item SET variant_code = '' WHERE variant_code IS NULL");
        $this->addSql('ALTER TABLE cardnext_quote_request_item DROP item_type, DROP configured_snapshot, CHANGE product_code product_code VARCHAR(64) NOT NULL, CHANGE variant_code variant_code VARCHAR(64) NOT NULL');
    }
}
