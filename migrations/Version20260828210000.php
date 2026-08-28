<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260828210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add stable public slugs, featured ordering and SEO fields to manufacturers'; }
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cardnext_manufacturer ADD slug VARCHAR(255) DEFAULT NULL, ADD featured TINYINT(1) DEFAULT 0 NOT NULL, ADD featured_position INT DEFAULT 100 NOT NULL, ADD seo_title VARCHAR(255) DEFAULT NULL, ADD seo_description VARCHAR(320) DEFAULT NULL");
        $this->addSql("UPDATE cardnext_manufacturer SET slug = LOWER(REPLACE(REPLACE(code, '_', '-'), ' ', '-'))");
        $this->addSql('ALTER TABLE cardnext_manufacturer MODIFY slug VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CN_MANUFACTURER_SLUG ON cardnext_manufacturer (slug)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CN_MANUFACTURER_SLUG ON cardnext_manufacturer');
        $this->addSql('ALTER TABLE cardnext_manufacturer DROP slug, DROP featured, DROP featured_position, DROP seo_title, DROP seo_description');
    }
}
