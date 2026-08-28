<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add typed per-product printer advisor profiles; no existing products are classified automatically';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cardnext_printer_advisor_profile (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, enabled TINYINT(1) DEFAULT 0 NOT NULL, priority INT DEFAULT 0 NOT NULL, min_annual_volume INT DEFAULT 0 NOT NULL, max_annual_volume INT DEFAULT NULL, single_sided TINYINT(1) DEFAULT 1 NOT NULL, duplex TINYINT(1) DEFAULT 0 NOT NULL, magnetic_stripe TINYINT(1) DEFAULT 0 NOT NULL, contact_chip TINYINT(1) DEFAULT 0 NOT NULL, rfid_nfc TINYINT(1) DEFAULT 0 NOT NULL, direct_printing TINYINT(1) DEFAULT 1 NOT NULL, retransfer TINYINT(1) DEFAULT 0 NOT NULL, lamination TINYINT(1) DEFAULT 0 NOT NULL, high_durability TINYINT(1) DEFAULT 0 NOT NULL, performance_class SMALLINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_CN_PRINTER_ADVISOR_PRODUCT (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cardnext_printer_advisor_profile ADD CONSTRAINT FK_CN_PRINTER_ADVISOR_PRODUCT FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_printer_advisor_profile');
    }
}
