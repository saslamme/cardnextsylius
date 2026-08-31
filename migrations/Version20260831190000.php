<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add localized configurator section, field, value, and lead-time content with a de_DE legacy backfill.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on MySQL.');

        $this->addSql('CREATE TABLE cardnext_configurator_section_translation (id INT AUTO_INCREMENT NOT NULL, section_id INT NOT NULL, locale VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_CNCST_SECTION (section_id), UNIQUE INDEX uniq_cn_cfg_section_locale (section_id, locale), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_field_translation (id INT AUTO_INCREMENT NOT NULL, field_id INT NOT NULL, locale VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, help_text LONGTEXT DEFAULT NULL, INDEX IDX_CNCFT_FIELD (field_id), UNIQUE INDEX uniq_cn_cfg_field_locale (field_id, locale), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_value_translation (id INT AUTO_INCREMENT NOT NULL, value_id INT NOT NULL, locale VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_CNCVT_VALUE (value_id), UNIQUE INDEX uniq_cn_cfg_value_locale (value_id, locale), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_lead_time_translation (id INT AUTO_INCREMENT NOT NULL, lead_time_id INT NOT NULL, locale VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_CNCLTT_LEAD (lead_time_id), UNIQUE INDEX uniq_cn_cfg_lead_time_locale (lead_time_id, locale), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cardnext_configurator_section_translation ADD CONSTRAINT FK_CNCST_SECTION FOREIGN KEY (section_id) REFERENCES cardnext_configurator_section (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_configurator_field_translation ADD CONSTRAINT FK_CNCFT_FIELD FOREIGN KEY (field_id) REFERENCES cardnext_configurator_field (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_configurator_value_translation ADD CONSTRAINT FK_CNCVT_VALUE FOREIGN KEY (value_id) REFERENCES cardnext_configurator_value (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_configurator_lead_time_translation ADD CONSTRAINT FK_CNCLTT_LEAD FOREIGN KEY (lead_time_id) REFERENCES cardnext_configurator_lead_time (id) ON DELETE CASCADE');
        $this->addSql("INSERT INTO cardnext_configurator_section_translation (section_id, locale, name, description) SELECT s.id, 'de_DE', s.name, s.description FROM cardnext_configurator_section s WHERE NOT EXISTS (SELECT 1 FROM cardnext_configurator_section_translation t WHERE t.section_id = s.id AND t.locale = 'de_DE')");
        $this->addSql("INSERT INTO cardnext_configurator_field_translation (field_id, locale, name, description, help_text) SELECT f.id, 'de_DE', f.name, f.description, f.help_text FROM cardnext_configurator_field f WHERE NOT EXISTS (SELECT 1 FROM cardnext_configurator_field_translation t WHERE t.field_id = f.id AND t.locale = 'de_DE')");
        $this->addSql("INSERT INTO cardnext_configurator_value_translation (value_id, locale, name, description) SELECT v.id, 'de_DE', v.name, v.description FROM cardnext_configurator_value v WHERE NOT EXISTS (SELECT 1 FROM cardnext_configurator_value_translation t WHERE t.value_id = v.id AND t.locale = 'de_DE')");
        $this->addSql("INSERT INTO cardnext_configurator_lead_time_translation (lead_time_id, locale, name, description) SELECT l.id, 'de_DE', l.name, l.description FROM cardnext_configurator_lead_time l WHERE NOT EXISTS (SELECT 1 FROM cardnext_configurator_lead_time_translation t WHERE t.lead_time_id = l.id AND t.locale = 'de_DE')");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Configurator translations and their production content must not be destroyed automatically.');
    }
}
