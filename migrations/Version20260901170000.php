<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260901170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds optional channel storefront branding configuration.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_channel ADD theme_key VARCHAR(64) DEFAULT NULL, ADD brand_name VARCHAR(128) DEFAULT NULL, ADD logo_path VARCHAR(255) DEFAULT NULL, ADD logo_dark_path VARCHAR(255) DEFAULT NULL, ADD favicon_path VARCHAR(255) DEFAULT NULL, ADD primary_color VARCHAR(7) DEFAULT NULL, ADD primary_hover_color VARCHAR(7) DEFAULT NULL, ADD primary_soft_color VARCHAR(7) DEFAULT NULL, ADD ink_color VARCHAR(7) DEFAULT NULL, ADD text_color VARCHAR(7) DEFAULT NULL, ADD footer_color VARCHAR(7) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_channel DROP theme_key, DROP brand_name, DROP logo_path, DROP logo_dark_path, DROP favicon_path, DROP primary_color, DROP primary_hover_color, DROP primary_soft_color, DROP ink_color, DROP text_color, DROP footer_color');
    }
}
