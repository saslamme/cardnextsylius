<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add channel-specific social media URLs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_channel ADD instagram_url VARCHAR(512) DEFAULT NULL, ADD facebook_url VARCHAR(512) DEFAULT NULL, ADD linkedin_url VARCHAR(512) DEFAULT NULL, ADD youtube_url VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_channel DROP instagram_url, DROP facebook_url, DROP linkedin_url, DROP youtube_url');
    }
}
