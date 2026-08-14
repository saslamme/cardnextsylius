<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813201500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds Cardnext manufacturers and product documents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE cardnext_manufacturer (
    id INT AUTO_INCREMENT NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    website VARCHAR(255) DEFAULT NULL,
    description LONGTEXT DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    position INT DEFAULT 0 NOT NULL,
    enabled TINYINT(1) DEFAULT 1 NOT NULL,
    UNIQUE INDEX UNIQ_CARDNEXT_MANUFACTURER_CODE (code),
    INDEX IDX_CARDNEXT_MANUFACTURER_NAME (name),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
ALTER TABLE sylius_product
    ADD manufacturer_id INT DEFAULT NULL,
    ADD CONSTRAINT FK_CARDNEXT_PRODUCT_MANUFACTURER
        FOREIGN KEY (manufacturer_id) REFERENCES cardnext_manufacturer (id) ON DELETE SET NULL
SQL);
        $this->addSql('CREATE INDEX IDX_CARDNEXT_PRODUCT_MANUFACTURER ON sylius_product (manufacturer_id)');

        $this->addSql(<<<'SQL'
CREATE TABLE cardnext_product_document (
    id INT AUTO_INCREMENT NOT NULL,
    product_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(64) NOT NULL,
    locale VARCHAR(10) DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    original_filename VARCHAR(255) DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    position INT DEFAULT 0 NOT NULL,
    enabled TINYINT(1) DEFAULT 1 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_CARDNEXT_DOCUMENT_PRODUCT (product_id),
    INDEX IDX_CARDNEXT_DOCUMENT_ENABLED_LOCALE (enabled, locale),
    PRIMARY KEY(id),
    CONSTRAINT FK_CARDNEXT_DOCUMENT_PRODUCT
        FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_product_document');
        $this->addSql('ALTER TABLE sylius_product DROP FOREIGN KEY FK_CARDNEXT_PRODUCT_MANUFACTURER');
        $this->addSql('DROP INDEX IDX_CARDNEXT_PRODUCT_MANUFACTURER ON sylius_product');
        $this->addSql('ALTER TABLE sylius_product DROP manufacturer_id');
        $this->addSql('DROP TABLE cardnext_manufacturer');
    }
}
