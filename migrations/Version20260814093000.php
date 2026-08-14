<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds Cardnext B2B and quantity price rules plus import counters.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE cardnext_variant_price_rule (
    id INT AUTO_INCREMENT NOT NULL,
    variant_id INT NOT NULL,
    channel_code VARCHAR(64) NOT NULL,
    customer_group_code VARCHAR(255) DEFAULT '' NOT NULL,
    min_quantity INT DEFAULT 1 NOT NULL,
    price INT NOT NULL,
    enabled TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_CN_PRICE_RULE_LOOKUP (variant_id, channel_code, enabled, min_quantity),
    UNIQUE INDEX UNIQ_CARDNEXT_VARIANT_PRICE_RULE (variant_id, channel_code, customer_group_code, min_quantity),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql('ALTER TABLE cardnext_variant_price_rule ADD CONSTRAINT FK_CN_PRICE_RULE_VARIANT FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE cardnext_product_import_run ADD price_rules_created INT DEFAULT NULL, ADD price_rules_updated INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_product_import_run DROP price_rules_created, DROP price_rules_updated');
        $this->addSql('DROP TABLE cardnext_variant_price_rule');
    }
}
