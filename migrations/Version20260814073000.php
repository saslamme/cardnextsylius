<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814073000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds stable import keys for product documents and document counters for import history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_product_document ADD import_key VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CARDNEXT_DOCUMENT_IMPORT_KEY ON cardnext_product_document (product_id, import_key)');
        $this->addSql('ALTER TABLE cardnext_product_import_run ADD documents_created INT DEFAULT NULL, ADD documents_updated INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_product_import_run DROP documents_created, DROP documents_updated');
        $this->addSql('DROP INDEX UNIQ_CARDNEXT_DOCUMENT_IMPORT_KEY ON cardnext_product_document');
        $this->addSql('ALTER TABLE cardnext_product_document DROP import_key');
    }
}
