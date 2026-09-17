<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Allow CMS blocks on SEO landing pages with catalog placement'; }
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.');
        $this->addSql("ALTER TABLE cardnext_cms_block ADD seo_landing_page_id INT DEFAULT NULL, ADD placement VARCHAR(32) DEFAULT 'content' NOT NULL, CHANGE page_id page_id INT DEFAULT NULL");
        $this->addSql('ALTER TABLE cardnext_cms_block ADD CONSTRAINT FK_CMS_BLOCK_SEO_LANDING FOREIGN KEY (seo_landing_page_id) REFERENCES cardnext_seo_landing_page (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_cms_block_seo_landing_page ON cardnext_cms_block (seo_landing_page_id)');
        $this->addSql('CREATE INDEX idx_cms_block_seo_render ON cardnext_cms_block (seo_landing_page_id, placement, enabled, position)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_cms_block DROP FOREIGN KEY FK_CMS_BLOCK_SEO_LANDING');
        $this->addSql('DROP INDEX idx_cms_block_seo_render ON cardnext_cms_block');
        $this->addSql('DROP INDEX idx_cms_block_seo_landing_page ON cardnext_cms_block');
        $this->addSql('DELETE FROM cardnext_cms_block WHERE page_id IS NULL');
        $this->addSql('ALTER TABLE cardnext_cms_block DROP seo_landing_page_id, DROP placement, CHANGE page_id page_id INT NOT NULL');
    }
}
