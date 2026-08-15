<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds manufacturer part number and EAN/GTIN identifiers to products for Cardnext search.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE sylius_product '
            . 'ADD manufacturer_part_number VARCHAR(128) DEFAULT NULL, '
            . 'ADD manufacturer_part_number_normalized VARCHAR(128) DEFAULT NULL, '
            . 'ADD ean VARCHAR(64) DEFAULT NULL, '
            . 'ADD ean_normalized VARCHAR(64) DEFAULT NULL'
        );

        $this->addSql(
            'CREATE INDEX IDX_CN_PRODUCT_MPN_NORM '
            . 'ON sylius_product (manufacturer_part_number_normalized)'
        );

        $this->addSql(
            'CREATE INDEX IDX_CN_PRODUCT_EAN_NORM '
            . 'ON sylius_product (ean_normalized)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_CN_PRODUCT_MPN_NORM ON sylius_product');
        $this->addSql('DROP INDEX IDX_CN_PRODUCT_EAN_NORM ON sylius_product');

        $this->addSql(
            'ALTER TABLE sylius_product '
            . 'DROP manufacturer_part_number, '
            . 'DROP manufacturer_part_number_normalized, '
            . 'DROP ean, '
            . 'DROP ean_normalized'
        );
    }
}
