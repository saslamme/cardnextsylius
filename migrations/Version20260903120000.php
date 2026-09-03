<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds addon-only products, linked addon order items, and the maintenance association type.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product ADD addon_only TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE sylius_order_item ADD parent_item_id INT DEFAULT NULL, ADD addon_type VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_CN_ORDER_ITEM_PARENT ON sylius_order_item (parent_item_id)');
        $this->addSql('ALTER TABLE sylius_order_item ADD CONSTRAINT FK_CN_ORDER_ITEM_PARENT FOREIGN KEY (parent_item_id) REFERENCES sylius_order_item (id) ON DELETE SET NULL');
        $this->addSql("INSERT INTO sylius_product_association_type (code, created_at, updated_at) SELECT 'maintenance_contracts', NOW(), NULL WHERE NOT EXISTS (SELECT 1 FROM sylius_product_association_type WHERE code = 'maintenance_contracts')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM sylius_product_association_type WHERE code = 'maintenance_contracts' AND NOT EXISTS (SELECT 1 FROM sylius_product_association a WHERE a.association_type_id = sylius_product_association_type.id)");
        $this->addSql('ALTER TABLE sylius_order_item DROP FOREIGN KEY FK_CN_ORDER_ITEM_PARENT');
        $this->addSql('DROP INDEX IDX_CN_ORDER_ITEM_PARENT ON sylius_order_item');
        $this->addSql('ALTER TABLE sylius_order_item DROP parent_item_id, DROP addon_type');
        $this->addSql('ALTER TABLE sylius_product DROP addon_only');
    }
}
