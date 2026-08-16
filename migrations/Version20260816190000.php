<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add device models, aliases and explicit product-to-device compatibility';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cardnext_device_model (id INT AUTO_INCREMENT NOT NULL, manufacturer_id INT NOT NULL, linked_product_id INT DEFAULT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, device_type VARCHAR(40) NOT NULL, status VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_DEVICE_CODE (code), UNIQUE INDEX UNIQ_CN_DEVICE_SLUG (slug), INDEX IDX_CN_DEVICE_MANUFACTURER (manufacturer_id), INDEX IDX_CN_DEVICE_PRODUCT (linked_product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE cardnext_device_model_alias (id INT AUTO_INCREMENT NOT NULL, device_model_id INT NOT NULL, alias VARCHAR(255) NOT NULL, normalized_alias VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_CN_DEVICE_ALIAS_NORMALIZED (normalized_alias), INDEX IDX_CN_DEVICE_ALIAS_MODEL (device_model_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("CREATE TABLE cardnext_product_device_compatibility (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, device_model_id INT NOT NULL, compatibility_type VARCHAR(40) NOT NULL, verified TINYINT(1) DEFAULT 0 NOT NULL, note VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_PRODUCT_DEVICE_COMPAT (product_id, device_model_id, compatibility_type), INDEX IDX_CN_PRODUCT_DEVICE_LOOKUP (product_id, enabled, position), INDEX IDX_CN_DEVICE_PRODUCT_LOOKUP (device_model_id, enabled), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cardnext_device_model ADD CONSTRAINT FK_CN_DEVICE_MANUFACTURER FOREIGN KEY (manufacturer_id) REFERENCES cardnext_manufacturer (id)');
        $this->addSql('ALTER TABLE cardnext_device_model ADD CONSTRAINT FK_CN_DEVICE_PRODUCT FOREIGN KEY (linked_product_id) REFERENCES sylius_product (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cardnext_device_model_alias ADD CONSTRAINT FK_CN_DEVICE_ALIAS_MODEL FOREIGN KEY (device_model_id) REFERENCES cardnext_device_model (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_product_device_compatibility ADD CONSTRAINT FK_CN_COMPAT_PRODUCT FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_product_device_compatibility ADD CONSTRAINT FK_CN_COMPAT_DEVICE FOREIGN KEY (device_model_id) REFERENCES cardnext_device_model (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_product_device_compatibility');
        $this->addSql('DROP TABLE cardnext_device_model_alias');
        $this->addSql('DROP TABLE cardnext_device_model');
    }
}
