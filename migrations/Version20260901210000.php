<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds per-channel email sender and reply-to settings.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE sylius_channel ADD email_sender_name VARCHAR(128) DEFAULT NULL, ADD email_sender_address VARCHAR(255) DEFAULT NULL, ADD email_reply_to_address VARCHAR(255) DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE sylius_channel DROP email_sender_name, DROP email_sender_address, DROP email_reply_to_address'); }
}
