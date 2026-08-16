<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260816150500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move product identifiers to variants and add Cardnext product metadata and ordering quantities';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sylius_product ADD model VARCHAR(255) DEFAULT NULL, ADD data_quality_status VARCHAR(32) DEFAULT 'imported' NOT NULL");

        $this->addSql("ALTER TABLE sylius_product_variant ADD manufacturer_part_number VARCHAR(128) DEFAULT NULL, ADD manufacturer_part_number_normalized VARCHAR(128) DEFAULT NULL, ADD gtin VARCHAR(64) DEFAULT NULL, ADD gtin_normalized VARCHAR(64) DEFAULT NULL, ADD minimum_order_quantity INT DEFAULT 1 NOT NULL, ADD order_increment INT DEFAULT 1 NOT NULL, ADD pack_quantity INT DEFAULT 1 NOT NULL");

        // Preserve existing identifiers on one deterministic variant before the
        // obsolete product columns are removed. Other variants remain empty and
        // can subsequently receive their own concrete MPN/GTIN values.
        $this->addSql(<<<'SQL'
            UPDATE sylius_product_variant variant
            INNER JOIN (
                SELECT product_id, MIN(id) AS variant_id
                FROM sylius_product_variant
                GROUP BY product_id
            ) first_variant ON first_variant.variant_id = variant.id
            INNER JOIN sylius_product product ON product.id = variant.product_id
            SET variant.manufacturer_part_number = product.manufacturer_part_number,
                variant.manufacturer_part_number_normalized = product.manufacturer_part_number_normalized,
                variant.gtin = product.ean,
                variant.gtin_normalized = product.ean_normalized
            SQL);

        $this->addSql('DROP INDEX IDX_CN_PRODUCT_MPN_NORM ON sylius_product');
        $this->addSql('DROP INDEX IDX_CN_PRODUCT_EAN_NORM ON sylius_product');

        $this->addSql('ALTER TABLE sylius_product DROP manufacturer_part_number, DROP manufacturer_part_number_normalized, DROP ean, DROP ean_normalized');

        $this->addSql('CREATE INDEX IDX_CN_VARIANT_MPN_NORMALIZED ON sylius_product_variant (manufacturer_part_number_normalized)');
        $this->addSql('CREATE INDEX IDX_CN_VARIANT_GTIN_NORMALIZED ON sylius_product_variant (gtin_normalized)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product ADD manufacturer_part_number VARCHAR(128) DEFAULT NULL, ADD manufacturer_part_number_normalized VARCHAR(128) DEFAULT NULL, ADD ean VARCHAR(64) DEFAULT NULL, ADD ean_normalized VARCHAR(64) DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE sylius_product product
            INNER JOIN (
                SELECT product_id, MIN(id) AS variant_id
                FROM sylius_product_variant
                GROUP BY product_id
            ) first_variant ON first_variant.product_id = product.id
            INNER JOIN sylius_product_variant variant ON variant.id = first_variant.variant_id
            SET product.manufacturer_part_number = variant.manufacturer_part_number,
                product.manufacturer_part_number_normalized = variant.manufacturer_part_number_normalized,
                product.ean = variant.gtin,
                product.ean_normalized = variant.gtin_normalized
            SQL);

        $this->addSql('CREATE INDEX IDX_CN_PRODUCT_MPN_NORM ON sylius_product (manufacturer_part_number_normalized)');
        $this->addSql('CREATE INDEX IDX_CN_PRODUCT_EAN_NORM ON sylius_product (ean_normalized)');

        $this->addSql('DROP INDEX IDX_CN_VARIANT_MPN_NORMALIZED ON sylius_product_variant');
        $this->addSql('DROP INDEX IDX_CN_VARIANT_GTIN_NORMALIZED ON sylius_product_variant');

        $this->addSql('ALTER TABLE sylius_product_variant DROP manufacturer_part_number, DROP manufacturer_part_number_normalized, DROP gtin, DROP gtin_normalized, DROP minimum_order_quantity, DROP order_increment, DROP pack_quantity');

        $this->addSql('ALTER TABLE sylius_product DROP model, DROP data_quality_status');
    }
}
