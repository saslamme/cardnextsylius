<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark the existing quote requested delivery date as Doctrine date_immutable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cardnext_quote_request CHANGE requested_delivery_date requested_delivery_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_quote_request CHANGE requested_delivery_date requested_delivery_date DATE DEFAULT NULL');
    }
}
