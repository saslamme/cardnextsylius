<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds channel- and locale-specific homepage editorial content.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cardnext_channel_homepage_content (id INT AUTO_INCREMENT NOT NULL, channel_id INT NOT NULL, locale_code VARCHAR(16) NOT NULL, hero_kicker VARCHAR(255) DEFAULT NULL, hero_title VARCHAR(255) DEFAULT NULL, hero_text LONGTEXT DEFAULT NULL, intro_kicker VARCHAR(255) DEFAULT NULL, intro_title VARCHAR(255) DEFAULT NULL, intro_text LONGTEXT DEFAULT NULL, why_kicker VARCHAR(255) DEFAULT NULL, why_title VARCHAR(255) DEFAULT NULL, why_text LONGTEXT DEFAULT NULL, cta_kicker VARCHAR(255) DEFAULT NULL, cta_title VARCHAR(255) DEFAULT NULL, cta_text LONGTEXT DEFAULT NULL, footer_text LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_HOMEPAGE_CHANNEL (channel_id), UNIQUE INDEX uniq_homepage_channel_locale (channel_id, locale_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cardnext_channel_homepage_content ADD CONSTRAINT FK_HOMEPAGE_CHANNEL FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_channel_homepage_content');
    }
}
