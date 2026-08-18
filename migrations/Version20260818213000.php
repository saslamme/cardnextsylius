<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add standalone configured order item snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cardnext_configured_order_item (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, configurator_code VARCHAR(100) NOT NULL, configurator_name VARCHAR(255) NOT NULL, locale_code VARCHAR(20) NOT NULL, channel_code VARCHAR(64) NOT NULL, currency_code VARCHAR(3) NOT NULL, quantity INT NOT NULL, lead_time_code VARCHAR(100) DEFAULT NULL, lead_time_name VARCHAR(255) DEFAULT NULL, working_days INT DEFAULT NULL, configuration_hash VARCHAR(64) NOT NULL, selections_snapshot JSON NOT NULL, price_breakdown_snapshot JSON NOT NULL, canonical_configuration JSON NOT NULL, base_unit_amount BIGINT NOT NULL, options_unit_amount BIGINT NOT NULL, unit_amount BIGINT NOT NULL, unit_total BIGINT NOT NULL, fixed_total BIGINT NOT NULL, percentage_total BIGINT NOT NULL, total BIGINT NOT NULL, snapshot_version INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CN_CONFIGURED_ORDER (order_id), INDEX IDX_CN_CONFIGURATION_HASH (configuration_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cardnext_configured_order_item ADD CONSTRAINT FK_CN_CONFIGURED_ORDER FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_configured_order_item');
    }
}
