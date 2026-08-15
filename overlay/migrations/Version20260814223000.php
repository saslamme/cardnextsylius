<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260814223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds manual homepage product selection and sort position.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE sylius_product '
            . 'ADD homepage_featured TINYINT(1) NOT NULL DEFAULT 0, '
            . 'ADD homepage_position INT NOT NULL DEFAULT 100'
        );

        $this->addSql(
            'CREATE INDEX IDX_CARDNEXT_HOMEPAGE_FEATURED_POSITION '
            . 'ON sylius_product (homepage_featured, homepage_position)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_CARDNEXT_HOMEPAGE_FEATURED_POSITION ON sylius_product');

        $this->addSql(
            'ALTER TABLE sylius_product '
            . 'DROP homepage_featured, '
            . 'DROP homepage_position'
        );
    }
}
