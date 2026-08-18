<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add checkout metadata to standalone configurators and configured order item snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_configurator ADD tax_category_id INT DEFAULT NULL, ADD shipping_required TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE cardnext_configurator ADD CONSTRAINT FK_CN_CONFIGURATOR_TAX_CATEGORY FOREIGN KEY (tax_category_id) REFERENCES sylius_tax_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CN_CONFIGURATOR_TAX_CATEGORY ON cardnext_configurator (tax_category_id)');
        $this->addSql('ALTER TABLE cardnext_configured_order_item ADD tax_category_code VARCHAR(255) DEFAULT NULL, ADD shipping_required TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_configurator DROP FOREIGN KEY FK_CN_CONFIGURATOR_TAX_CATEGORY');
        $this->addSql('DROP INDEX IDX_CN_CONFIGURATOR_TAX_CATEGORY ON cardnext_configurator');
        $this->addSql('ALTER TABLE cardnext_configurator DROP tax_category_id, DROP shipping_required');
        $this->addSql('ALTER TABLE cardnext_configured_order_item DROP tax_category_code, DROP shipping_required');
    }
}
