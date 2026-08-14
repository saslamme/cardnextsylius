<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813233500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds Cardnext product import history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE cardnext_product_import_run (
    id INT AUTO_INCREMENT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    status VARCHAR(32) NOT NULL,
    dry_run TINYINT(1) DEFAULT 0 NOT NULL,
    row_count INT DEFAULT NULL,
    products_created INT DEFAULT NULL,
    products_updated INT DEFAULT NULL,
    variants_created INT DEFAULT NULL,
    variants_updated INT DEFAULT NULL,
    warnings JSON DEFAULT NULL,
    error_message LONGTEXT DEFAULT NULL,
    user_identifier VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_CARDNEXT_IMPORT_CREATED (created_at),
    INDEX IDX_CARDNEXT_IMPORT_STATUS (status),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_product_import_run');
    }
}
