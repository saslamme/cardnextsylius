<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional channel navigation branding colors';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_channel ADD navigation_background_color VARCHAR(7) DEFAULT NULL, ADD navigation_text_color VARCHAR(7) DEFAULT NULL, ADD navigation_hover_color VARCHAR(7) DEFAULT NULL, ADD navigation_border_color VARCHAR(7) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_channel DROP navigation_background_color, DROP navigation_text_color, DROP navigation_hover_color, DROP navigation_border_color');
    }
}
