<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align custom index names with Doctrine metadata and remove obsolete unmapped indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on MySQL.');

        $this->addSql('DROP INDEX IDX_CARDNEXT_HOMEPAGE_FEATURED_POSITION ON sylius_product');
        $this->addSql('ALTER TABLE sylius_product RENAME INDEX idx_cardnext_product_manufacturer TO IDX_677B9B74A23B42D');
        $this->addSql('ALTER TABLE cardnext_device_model RENAME INDEX uniq_cn_device_code TO UNIQ_FCB93F6277153098, RENAME INDEX uniq_cn_device_slug TO UNIQ_FCB93F62989D9B62, RENAME INDEX idx_cn_device_manufacturer TO IDX_FCB93F62A23B42D, RENAME INDEX idx_cn_device_product TO IDX_FCB93F62D240BD1D');
        $this->addSql('ALTER TABLE cardnext_printer_advisor_profile RENAME INDEX uniq_cn_printer_advisor_product TO UNIQ_FBF1A8F34584665A');
        $this->addSql('DROP INDEX IDX_CARDNEXT_DOCUMENT_ENABLED_LOCALE ON cardnext_product_document');
        $this->addSql('DROP INDEX UNIQ_CARDNEXT_DOCUMENT_IMPORT_KEY ON cardnext_product_document');
        $this->addSql('ALTER TABLE cardnext_product_document RENAME INDEX idx_cardnext_document_product TO IDX_F85B1964584665A');
        $this->addSql('ALTER TABLE cardnext_device_model_alias RENAME INDEX idx_cn_device_alias_model TO IDX_D1BD8DAAF741EEC7');
        $this->addSql('DROP INDEX IDX_CARDNEXT_MANUFACTURER_NAME ON cardnext_manufacturer');
        $this->addSql('ALTER TABLE cardnext_manufacturer RENAME INDEX uniq_cardnext_manufacturer_code TO UNIQ_D0A34B0077153098, RENAME INDEX uniq_cn_manufacturer_slug TO UNIQ_D0A34B00989D9B62');
        $this->addSql('ALTER TABLE cardnext_customer_variant_price_rule RENAME INDEX fk_cn_customer_price_customer TO IDX_94A4E3C79395C3F3');
        $this->addSql('ALTER TABLE cardnext_configurator_taxon RENAME INDEX idx_cn_cfg_taxon_parent TO IDX_10439739DF663348, RENAME INDEX idx_cn_cfg_taxon_taxon TO IDX_10439739DE13F470');
        $this->addSql('ALTER TABLE cardnext_configurator RENAME INDEX uniq_cn_configurator_code TO UNIQ_3F8FAAEC77153098, RENAME INDEX idx_cn_configurator_tax_category TO IDX_3F8FAAEC9DF894ED');
        $this->addSql('ALTER TABLE cardnext_configurator_channel RENAME INDEX idx_cn_cfg_channel_configurator TO IDX_2D825A6EDF663348, RENAME INDEX idx_cn_cfg_channel_channel TO IDX_2D825A6E72F5A1AA');
        $this->addSql('ALTER TABLE cardnext_configurator_price_rule RENAME INDEX idx_cn_cfg_rule_value TO IDX_2E958B39F920BBA2, RENAME INDEX idx_cn_cfg_rule_channel TO IDX_2E958B3972F5A1AA, RENAME INDEX idx_cn_cfg_rule_multiplier TO IDX_2E958B39E6C71B56');
        $this->addSql('ALTER TABLE cardnext_configurator_translation RENAME INDEX idx_cn_cfg_translation_parent TO IDX_521F06BBDF663348');
        $this->addSql('ALTER TABLE cardnext_configurator_image RENAME INDEX idx_cn_cfg_image_parent TO IDX_8E19B0CDDF663348');
        $this->addSql('ALTER TABLE cardnext_configurator_dependency RENAME INDEX idx_cn_cfg_dep_source TO IDX_3A47804A7173162, RENAME INDEX idx_cn_cfg_dep_target_field TO IDX_3A47804A9E9CD7D9, RENAME INDEX idx_cn_cfg_dep_target_value TO IDX_3A47804A238B6BCB');
        $this->addSql('ALTER TABLE cardnext_maintenance_contract RENAME INDEX idx_cn_maintenance_customer TO IDX_6E6EBEC19395C3F3');
        $this->addSql('ALTER TABLE cardnext_quote_item RENAME INDEX idx_cn_offer_item_product TO IDX_A20BA64F4584665A, RENAME INDEX idx_cn_offer_item_variant TO IDX_A20BA64F3B69A9AF');
        $this->addSql('ALTER TABLE cardnext_quote_request RENAME INDEX uniq_cn_quote_number TO UNIQ_5BF0104996901F54, RENAME INDEX idx_cn_quote_customer TO IDX_5BF010499395C3F3');
        $this->addSql('ALTER TABLE cardnext_quote_request_item RENAME INDEX idx_cn_qi_quote TO IDX_AB4FCFEDF229C21E, RENAME INDEX idx_cn_qi_product TO IDX_AB4FCFED4584665A, RENAME INDEX idx_cn_qi_variant TO IDX_AB4FCFED3B69A9AF');
        $this->addSql('ALTER TABLE cardnext_quote RENAME INDEX idx_cn_offer_created_by TO IDX_34DD22C1B03A8386, RENAME INDEX idx_cn_offer_updated_by TO IDX_34DD22C1896DBBDE, RENAME INDEX uniq_cn_quote_order TO UNIQ_34DD22C18D9F6D38, RENAME INDEX idx_cn_quote_converter TO IDX_34DD22C1A926919B');
        $this->addSql('ALTER TABLE cardnext_quote_request_history RENAME INDEX idx_cn_qh_quote TO IDX_58AF33FDF229C21E');
        $this->addSql('ALTER TABLE cardnext_legal_page_channel RENAME INDEX idx_cn_legal_page TO IDX_F393E39ECB7DFD8A, RENAME INDEX idx_cn_legal_channel TO IDX_F393E39E72F5A1AA');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restoring obsolete indexes could reintroduce stale uniqueness constraints.');
    }
}
