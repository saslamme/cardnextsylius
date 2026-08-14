<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814123500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a translatable bottom description to Sylius taxons.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE sylius_taxon_translation ADD bottom_description LONGTEXT DEFAULT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE sylius_taxon_translation DROP bottom_description'
        );
    }
}
