<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates editable Cardnext legal pages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE cardnext_legal_page ('
            . 'id INT AUTO_INCREMENT NOT NULL, '
            . 'code VARCHAR(32) NOT NULL, '
            . 'locale_code VARCHAR(12) NOT NULL, '
            . 'title VARCHAR(255) NOT NULL, '
            . 'content LONGTEXT NOT NULL, '
            . 'meta_title VARCHAR(255) DEFAULT NULL, '
            . 'meta_description VARCHAR(500) DEFAULT NULL, '
            . 'updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', '
            . 'UNIQUE INDEX UNIQ_CARDNEXT_LEGAL_PAGE_CODE_LOCALE (code, locale_code), '
            . 'PRIMARY KEY(id)'
            . ') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_legal_page');
    }
}
