<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add administrable SEO filter landing pages'; }
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.');
        $this->addSql('CREATE TABLE cardnext_seo_landing_page (id INT AUTO_INCREMENT NOT NULL, channel_id INT NOT NULL, base_taxon_id INT NOT NULL, internal_name VARCHAR(255) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, locale VARCHAR(12) NOT NULL, path VARCHAR(255) NOT NULL, h1 VARCHAR(255) NOT NULL, meta_title VARCHAR(255) NOT NULL, meta_description VARCHAR(500) DEFAULT NULL, top_content LONGTEXT DEFAULT NULL, bottom_content LONGTEXT DEFAULT NULL, robots_index TINYINT(1) DEFAULT 1 NOT NULL, robots_follow TINYINT(1) DEFAULT 1 NOT NULL, canonical_url VARCHAR(2048) DEFAULT NULL, filter_definition JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_SEO_LANDING_CHANNEL (channel_id), INDEX IDX_SEO_LANDING_TAXON (base_taxon_id), UNIQUE INDEX uniq_seo_landing_channel_locale_path (channel_id, locale, path), PRIMARY KEY(id), CONSTRAINT FK_SEO_LANDING_CHANNEL FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE, CONSTRAINT FK_SEO_LANDING_TAXON FOREIGN KEY (base_taxon_id) REFERENCES sylius_taxon (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
    public function down(Schema $schema): void { $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.'); $this->addSql('DROP TABLE cardnext_seo_landing_page'); }
}
