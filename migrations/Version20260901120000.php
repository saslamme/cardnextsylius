<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the nullable primary sales channel to customers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer ADD sales_channel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_customer ADD CONSTRAINT FK_CUSTOMER_SALES_CHANNEL FOREIGN KEY (sales_channel_id) REFERENCES sylius_channel (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CUSTOMER_SALES_CHANNEL ON sylius_customer (sales_channel_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer DROP FOREIGN KEY FK_CUSTOMER_SALES_CHANNEL');
        $this->addSql('DROP INDEX IDX_CUSTOMER_SALES_CHANNEL ON sylius_customer');
        $this->addSql('ALTER TABLE sylius_customer DROP sales_channel_id');
    }
}
