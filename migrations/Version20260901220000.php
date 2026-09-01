<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel- and locale-specific homepage SEO fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_channel_homepage_content ADD meta_title VARCHAR(255) DEFAULT NULL, ADD meta_description VARCHAR(320) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_channel_homepage_content DROP meta_title, DROP meta_description');
    }
}
