<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Model each ERP maintenance contract as one header with multiple devices.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on MySQL/MariaDB.');

        $this->addSql("CREATE TABLE cardnext_maintenance_contract_device (id INT AUTO_INCREMENT NOT NULL, contract_id INT NOT NULL, serial_number VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_MAINTENANCE_DEVICE (contract_id, serial_number), INDEX IDX_CN_MAINTENANCE_DEVICE_SERIAL (serial_number), INDEX IDX_CN_MAINTENANCE_DEVICE_CONTRACT (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cardnext_maintenance_contract_device ADD CONSTRAINT FK_CN_MAINTENANCE_DEVICE_CONTRACT FOREIGN KEY (contract_id) REFERENCES cardnext_maintenance_contract (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO cardnext_maintenance_contract_device (contract_id, serial_number, created_at, updated_at) SELECT id, TRIM(serial_number), created_at, updated_at FROM cardnext_maintenance_contract');
        $this->addSql('DROP INDEX IDX_CN_MAINTENANCE_SERIAL ON cardnext_maintenance_contract');
        $this->addSql('ALTER TABLE cardnext_maintenance_contract DROP serial_number');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on MySQL/MariaDB.');

        $contractsWithoutExactlyOneDevice = $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT contract_header.id FROM cardnext_maintenance_contract contract_header LEFT JOIN cardnext_maintenance_contract_device device ON device.contract_id = contract_header.id GROUP BY contract_header.id HAVING COUNT(device.id) <> 1) unsafe_contracts');
        $this->abortIf(!in_array($contractsWithoutExactlyOneDevice, [0, '0'], true), 'Down migration is unsafe: every contract must have exactly one device, otherwise serial numbers would be lost.');

        $this->addSql('ALTER TABLE cardnext_maintenance_contract ADD serial_number VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE cardnext_maintenance_contract contract_header INNER JOIN cardnext_maintenance_contract_device device ON device.contract_id = contract_header.id SET contract_header.serial_number = device.serial_number');
        $this->addSql('ALTER TABLE cardnext_maintenance_contract MODIFY serial_number VARCHAR(255) NOT NULL');
        $this->addSql('CREATE INDEX IDX_CN_MAINTENANCE_SERIAL ON cardnext_maintenance_contract (serial_number)');
        $this->addSql('DROP TABLE cardnext_maintenance_contract_device');
    }
}
