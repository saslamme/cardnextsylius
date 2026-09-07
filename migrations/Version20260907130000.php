<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema; use Doctrine\Migrations\AbstractMigration;
final class Version20260907130000 extends AbstractMigration
{
 public function getDescription():string{return 'Add local Freshdesk support-case mapping';}
 public function up(Schema $schema):void{$this->addSql('CREATE TABLE cardnext_support_case (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, order_id INT DEFAULT NULL, product_id INT DEFAULT NULL, maintenance_contract_id INT DEFAULT NULL, freshdesk_ticket_id BIGINT NOT NULL, freshdesk_requester_id BIGINT NOT NULL, channel_code VARCHAR(64) NOT NULL, service_type VARCHAR(64) NOT NULL, serial_number VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_SUPPORT_FRESHDESK_TICKET (freshdesk_ticket_id), INDEX IDX_SUPPORT_CUSTOMER (customer_id), INDEX IDX_SUPPORT_CHANNEL (channel_code), INDEX IDX_SUPPORT_TYPE (service_type), INDEX IDX_SUPPORT_SERIAL (serial_number), INDEX IDX_SUPPORT_ORDER (order_id), INDEX IDX_SUPPORT_PRODUCT (product_id), INDEX IDX_SUPPORT_MAINTENANCE (maintenance_contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');$this->addSql('ALTER TABLE cardnext_support_case ADD CONSTRAINT FK_SUPPORT_CUSTOMER FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_SUPPORT_ORDER FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE SET NULL, ADD CONSTRAINT FK_SUPPORT_PRODUCT FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE SET NULL, ADD CONSTRAINT FK_SUPPORT_MAINTENANCE FOREIGN KEY (maintenance_contract_id) REFERENCES cardnext_maintenance_contract (id) ON DELETE SET NULL');}
 public function down(Schema $schema):void{$this->addSql('DROP TABLE cardnext_support_case');}
}
