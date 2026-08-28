<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds channel assignments to legal pages and assigns existing pages to CARDNEXT_DE.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne("SELECT COUNT(*) FROM sylius_channel WHERE code = 'CARDNEXT_DE'") !== 1,
            'Cannot assign existing legal pages: the Sylius channel CARDNEXT_DE does not exist.',
        );

        $this->addSql('CREATE TABLE cardnext_legal_page_channel (legal_page_id INT NOT NULL, channel_id INT NOT NULL, INDEX IDX_CN_LEGAL_PAGE (legal_page_id), INDEX IDX_CN_LEGAL_CHANNEL (channel_id), PRIMARY KEY(legal_page_id, channel_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cardnext_legal_page_channel ADD CONSTRAINT FK_CN_LEGAL_PAGE FOREIGN KEY (legal_page_id) REFERENCES cardnext_legal_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_legal_page_channel ADD CONSTRAINT FK_CN_LEGAL_CHANNEL FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
        $this->addSql("INSERT INTO cardnext_legal_page_channel (legal_page_id, channel_id) SELECT page.id, channel.id FROM cardnext_legal_page page INNER JOIN sylius_channel channel ON channel.code = 'CARDNEXT_DE'");
        $this->addSql('DROP INDEX UNIQ_CARDNEXT_LEGAL_PAGE_CODE_LOCALE ON cardnext_legal_page');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_legal_page_channel');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CARDNEXT_LEGAL_PAGE_CODE_LOCALE ON cardnext_legal_page (code, locale_code)');
    }
}
