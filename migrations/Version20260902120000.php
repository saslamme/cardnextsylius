<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds two channel- and locale-specific homepage promo cards.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cardnext_channel_homepage_content
            ADD printer_guide_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD printer_guide_eyebrow VARCHAR(255) DEFAULT NULL,
            ADD printer_guide_headline VARCHAR(255) DEFAULT NULL,
            ADD printer_guide_text LONGTEXT DEFAULT NULL,
            ADD printer_guide_button_label VARCHAR(255) DEFAULT NULL,
            ADD printer_guide_url VARCHAR(2048) DEFAULT NULL,
            ADD printer_guide_image_path VARCHAR(255) DEFAULT NULL,
            ADD printer_guide_image_alt VARCHAR(255) DEFAULT NULL,
            ADD printer_guide_badge VARCHAR(255) DEFAULT NULL,
            ADD configurator_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD configurator_eyebrow VARCHAR(255) DEFAULT NULL,
            ADD configurator_headline VARCHAR(255) DEFAULT NULL,
            ADD configurator_text LONGTEXT DEFAULT NULL,
            ADD configurator_button_label VARCHAR(255) DEFAULT NULL,
            ADD configurator_url VARCHAR(2048) DEFAULT NULL,
            ADD configurator_image_path VARCHAR(255) DEFAULT NULL,
            ADD configurator_image_alt VARCHAR(255) DEFAULT NULL,
            ADD configurator_badge VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_channel_homepage_content
            DROP printer_guide_enabled, DROP printer_guide_eyebrow, DROP printer_guide_headline,
            DROP printer_guide_text, DROP printer_guide_button_label, DROP printer_guide_url,
            DROP printer_guide_image_path, DROP printer_guide_image_alt, DROP printer_guide_badge,
            DROP configurator_enabled, DROP configurator_eyebrow, DROP configurator_headline,
            DROP configurator_text, DROP configurator_button_label, DROP configurator_url,
            DROP configurator_image_path, DROP configurator_image_alt, DROP configurator_badge');
    }
}
