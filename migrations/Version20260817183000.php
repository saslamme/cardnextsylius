<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add first-class product kinds and enforce one configurator per product without replacing legacy data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sylius_product ADD product_kind VARCHAR(20) DEFAULT 'standard' NOT NULL");
        $this->addSql("UPDATE sylius_product p INNER JOIN cardnext_configurator c ON c.product_id = p.id SET p.product_kind = 'configurable'");

        // This guard intentionally fails before the schema changes with the named
        // unique key if legacy data references a product more than once.
        $this->addSql('CREATE TEMPORARY TABLE cardnext_configurator_product_guard (product_id INT NOT NULL, CONSTRAINT migration_duplicate_product_configurator UNIQUE (product_id))');
        $this->addSql('INSERT INTO cardnext_configurator_product_guard (product_id) SELECT product_id FROM cardnext_configurator WHERE product_id IS NOT NULL');
        $this->addSql('DROP TEMPORARY TABLE cardnext_configurator_product_guard');

        $this->addSql('ALTER TABLE cardnext_configurator DROP FOREIGN KEY FK_712BE502A8B7');
        $this->addSql('DROP INDEX IDX_CN_CONFIGURATOR_PRODUCT ON cardnext_configurator');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CN_CONFIGURATOR_PRODUCT ON cardnext_configurator (product_id)');
        $this->addSql('ALTER TABLE cardnext_configurator ADD CONSTRAINT FK_712BE502A8B7 FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_configurator DROP FOREIGN KEY FK_712BE502A8B7');
        $this->addSql('DROP INDEX UNIQ_CN_CONFIGURATOR_PRODUCT ON cardnext_configurator');
        $this->addSql('CREATE INDEX IDX_CN_CONFIGURATOR_PRODUCT ON cardnext_configurator (product_id)');
        $this->addSql('ALTER TABLE cardnext_configurator ADD CONSTRAINT FK_712BE502A8B7 FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE sylius_product DROP product_kind');
    }
}
