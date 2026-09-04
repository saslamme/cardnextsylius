<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add channel-aware product bundles and independent order-item bundle grouping.'; }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on MySQL.');
        $this->addSql('CREATE TABLE cardnext_product_bundle (id INT AUTO_INCREMENT NOT NULL, main_product_id INT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CN_BUNDLE_CODE (code), INDEX IDX_CN_BUNDLE_PRODUCT (main_product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_product_bundle_item (id INT AUTO_INCREMENT NOT NULL, bundle_id INT NOT NULL, variant_id INT NOT NULL, quantity INT NOT NULL, position INT DEFAULT 0 NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CN_BUNDLE_ITEM_BUNDLE (bundle_id), INDEX IDX_CN_BUNDLE_ITEM_VARIANT (variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_product_bundle_channel (id INT AUTO_INCREMENT NOT NULL, bundle_id INT NOT NULL, channel_id INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, discount_type VARCHAR(16) DEFAULT \'NONE\' NOT NULL, fixed_discount INT DEFAULT NULL, percentage_discount INT DEFAULT NULL, UNIQUE INDEX UNIQ_CN_BUNDLE_CHANNEL (bundle_id, channel_id), INDEX IDX_CN_BUNDLE_CHANNEL_CHANNEL (channel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cardnext_product_bundle ADD CONSTRAINT FK_CN_BUNDLE_PRODUCT FOREIGN KEY (main_product_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_product_bundle_item ADD CONSTRAINT FK_CN_BUNDLE_ITEM_BUNDLE FOREIGN KEY (bundle_id) REFERENCES cardnext_product_bundle (id) ON DELETE CASCADE, ADD CONSTRAINT FK_CN_BUNDLE_ITEM_VARIANT FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE cardnext_product_bundle_channel ADD CONSTRAINT FK_CN_BUNDLE_CHANNEL_BUNDLE FOREIGN KEY (bundle_id) REFERENCES cardnext_product_bundle (id) ON DELETE CASCADE, ADD CONSTRAINT FK_CN_BUNDLE_CHANNEL_CHANNEL FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_order_item ADD bundle_id INT DEFAULT NULL, ADD bundle_group_key VARCHAR(36) DEFAULT NULL, ADD bundle_role VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order_item ADD CONSTRAINT FK_CN_ORDER_ITEM_BUNDLE FOREIGN KEY (bundle_id) REFERENCES cardnext_product_bundle (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CN_ORDER_ITEM_BUNDLE ON sylius_order_item (bundle_id)');
        $this->addSql('CREATE INDEX IDX_CN_ORDER_ITEM_BUNDLE_GROUP ON sylius_order_item (bundle_group_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order_item DROP FOREIGN KEY FK_CN_ORDER_ITEM_BUNDLE');
        $this->addSql('DROP INDEX IDX_CN_ORDER_ITEM_BUNDLE ON sylius_order_item');
        $this->addSql('DROP INDEX IDX_CN_ORDER_ITEM_BUNDLE_GROUP ON sylius_order_item');
        $this->addSql('ALTER TABLE sylius_order_item DROP bundle_id, DROP bundle_group_key, DROP bundle_role');
        $this->addSql('DROP TABLE cardnext_product_bundle_channel');
        $this->addSql('DROP TABLE cardnext_product_bundle_item');
        $this->addSql('DROP TABLE cardnext_product_bundle');
    }
}
