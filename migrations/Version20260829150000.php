<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260829150000 extends AbstractMigration
{
 public function getDescription(): string { return 'Add immutable quote recipient, release and tax snapshots for phase 2b'; }
 public function up(Schema $schema): void { $this->addSql("ALTER TABLE cardnext_quote ADD customer_street VARCHAR(255) DEFAULT NULL, ADD customer_house_number VARCHAR(32) DEFAULT NULL, ADD customer_postal_code VARCHAR(32) DEFAULT NULL, ADD customer_city VARCHAR(128) DEFAULT NULL, ADD customer_country_code VARCHAR(2) DEFAULT NULL, ADD customer_number VARCHAR(64) DEFAULT NULL, ADD customer_phone VARCHAR(64) DEFAULT NULL, ADD project_reference VARCHAR(255) DEFAULT NULL, ADD customer_purchase_order_number VARCHAR(128) DEFAULT NULL, ADD quote_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', ADD ready_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD default_tax_rate INT DEFAULT 0 NOT NULL, ADD tax_note LONGTEXT DEFAULT NULL"); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE cardnext_quote DROP customer_street, DROP customer_house_number, DROP customer_postal_code, DROP customer_city, DROP customer_country_code, DROP customer_number, DROP customer_phone, DROP project_reference, DROP customer_purchase_order_number, DROP quote_date, DROP ready_at, DROP default_tax_rate, DROP tax_note'); }
}
