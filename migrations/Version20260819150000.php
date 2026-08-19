<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preselected default flag to configurator lead times';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_configurator_lead_time ADD preselected TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_configurator_lead_time DROP preselected');
    }
}
