<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds Cardnext B2B customer profiles with company, ERP and payment metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE cardnext_customer_b2b_profile (
    id INT AUTO_INCREMENT NOT NULL,
    customer_id INT NOT NULL,
    customer_number VARCHAR(64) DEFAULT NULL,
    company_name VARCHAR(255) DEFAULT NULL,
    vat_number VARCHAR(64) DEFAULT NULL,
    erp_customer_number VARCHAR(64) DEFAULT NULL,
    contact_person VARCHAR(255) DEFAULT NULL,
    invoice_allowed TINYINT(1) DEFAULT 0 NOT NULL,
    credit_limit INT DEFAULT NULL,
    credit_limit_currency VARCHAR(3) DEFAULT NULL,
    payment_term_days INT DEFAULT NULL,
    purchase_order_required TINYINT(1) DEFAULT 0 NOT NULL,
    enabled TINYINT(1) DEFAULT 1 NOT NULL,
    notes LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX UNIQ_CN_B2B_CUSTOMER (customer_id),
    UNIQUE INDEX UNIQ_CN_B2B_CUSTOMER_NUMBER (customer_number),
    UNIQUE INDEX UNIQ_CN_B2B_ERP_NUMBER (erp_customer_number),
    INDEX IDX_CN_B2B_ENABLED (enabled),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql('ALTER TABLE cardnext_customer_b2b_profile ADD CONSTRAINT FK_CN_B2B_CUSTOMER FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_customer_b2b_profile');
    }
}
