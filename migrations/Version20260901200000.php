<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds configurable homepage image paths.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_channel_homepage_content ADD hero_image_path VARCHAR(255) DEFAULT NULL, ADD intro_image_path VARCHAR(255) DEFAULT NULL, ADD cta_image_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_channel_homepage_content DROP hero_image_path, DROP intro_image_path, DROP cta_image_path');
    }
}
