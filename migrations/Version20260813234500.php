<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds manufacturer counters to Cardnext product import history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_product_import_run ADD manufacturers_created INT DEFAULT NULL, ADD manufacturers_updated INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_product_import_run DROP manufacturers_created, DROP manufacturers_updated');
    }
}
