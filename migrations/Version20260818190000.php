<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional lead-time source to configurator price rules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_configurator_price_rule ADD lead_time_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cardnext_configurator_price_rule ADD CONSTRAINT FK_CN_CFG_RULE_LEAD FOREIGN KEY (lead_time_id) REFERENCES cardnext_configurator_lead_time (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_CN_CFG_RULE_LEAD ON cardnext_configurator_price_rule (lead_time_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_configurator_price_rule DROP FOREIGN KEY FK_CN_CFG_RULE_LEAD');
        $this->addSql('DROP INDEX IDX_CN_CFG_RULE_LEAD ON cardnext_configurator_price_rule');
        $this->addSql('ALTER TABLE cardnext_configurator_price_rule DROP lead_time_id');
    }
}
