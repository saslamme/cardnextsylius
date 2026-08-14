<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814083500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds product compatibility relations and compatibility counters to Cardnext imports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE cardnext_product_compatibility (
    id INT AUTO_INCREMENT NOT NULL,
    source_product_id INT NOT NULL,
    target_product_id INT NOT NULL,
    relation_type VARCHAR(40) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    position INT DEFAULT 0 NOT NULL,
    enabled TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_CN_COMPAT_SOURCE (source_product_id, enabled, position),
    INDEX IDX_CN_COMPAT_TARGET (target_product_id, enabled, position),
    UNIQUE INDEX UNIQ_CARDNEXT_PRODUCT_COMPATIBILITY (source_product_id, target_product_id, relation_type),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql('ALTER TABLE cardnext_product_compatibility ADD CONSTRAINT FK_CN_COMPAT_SOURCE FOREIGN KEY (source_product_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_product_compatibility ADD CONSTRAINT FK_CN_COMPAT_TARGET FOREIGN KEY (target_product_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_product_import_run ADD compatibilities_created INT DEFAULT NULL, ADD compatibilities_updated INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_product_import_run DROP compatibilities_created, DROP compatibilities_updated');
        $this->addSql('DROP TABLE cardnext_product_compatibility');
    }
}
