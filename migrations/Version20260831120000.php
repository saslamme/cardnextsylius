<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260831120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add the ERP-synchronized maintenance-contract projection'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cardnext_maintenance_contract (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, erp_contract_id VARCHAR(128) NOT NULL, erp_customer_number VARCHAR(64) NOT NULL, serial_number VARCHAR(255) NOT NULL, printer_model VARCHAR(255) DEFAULT NULL, contract_reference VARCHAR(255) DEFAULT NULL, starts_at DATE NOT NULL COMMENT '(DC2Type:date_immutable)', ends_at DATE NOT NULL COMMENT '(DC2Type:date_immutable)', last_synced_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', source_updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', internal_note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_MAINTENANCE_ERP_ID (erp_contract_id), INDEX IDX_CN_MAINTENANCE_CUSTOMER (customer_id), INDEX IDX_CN_MAINTENANCE_ERP_CUSTOMER (erp_customer_number), INDEX IDX_CN_MAINTENANCE_SERIAL (serial_number), INDEX IDX_CN_MAINTENANCE_ENDS (ends_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cardnext_maintenance_contract ADD CONSTRAINT FK_CN_MAINTENANCE_CUSTOMER FOREIGN KEY (customer_id) REFERENCES sylius_customer (id)');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE cardnext_maintenance_contract'); }
}
