<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create version-ready editable quotes and immutable quote item snapshots'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cardnext_quote (id INT AUTO_INCREMENT NOT NULL, quote_request_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, number VARCHAR(20) NOT NULL, version INT NOT NULL, status VARCHAR(16) NOT NULL, channel_code VARCHAR(64) NOT NULL, locale_code VARCHAR(12) NOT NULL, currency_code VARCHAR(3) NOT NULL, customer_company VARCHAR(255) NOT NULL, customer_contact_name VARCHAR(255) NOT NULL, customer_email VARCHAR(254) NOT NULL, valid_until DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', delivery_terms LONGTEXT DEFAULT NULL, payment_terms LONGTEXT DEFAULT NULL, customer_note LONGTEXT DEFAULT NULL, internal_note LONGTEXT DEFAULT NULL, subtotal INT NOT NULL, discount_total INT NOT NULL, shipping_total INT NOT NULL, service_total INT NOT NULL, tax_total INT NOT NULL, grand_total INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_OFFER_NUMBER_VERSION (number, version), INDEX IDX_CN_OFFER_REQUEST (quote_request_id), INDEX IDX_CN_OFFER_STATUS (status), INDEX IDX_CN_OFFER_CREATED (created_at), INDEX IDX_CN_OFFER_CREATED_BY (created_by_id), INDEX IDX_CN_OFFER_UPDATED_BY (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE cardnext_quote_item (id INT AUTO_INCREMENT NOT NULL, quote_id INT NOT NULL, product_id INT DEFAULT NULL, variant_id INT DEFAULT NULL, position INT NOT NULL, product_code VARCHAR(64) DEFAULT NULL, variant_code VARCHAR(64) DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, quantity INT NOT NULL, original_unit_price INT DEFAULT NULL, unit_price INT NOT NULL, discount_percent INT DEFAULT NULL, discount_amount INT DEFAULT NULL, line_subtotal INT NOT NULL, line_discount INT NOT NULL, line_total INT NOT NULL, tax_rate INT DEFAULT NULL, item_type VARCHAR(16) NOT NULL, INDEX IDX_CN_OFFER_ITEM_POSITION (quote_id, position), INDEX IDX_CN_OFFER_ITEM_PRODUCT (product_id), INDEX IDX_CN_OFFER_ITEM_VARIANT (variant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cardnext_quote ADD CONSTRAINT FK_CN_OFFER_REQUEST FOREIGN KEY (quote_request_id) REFERENCES cardnext_quote_request (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_CN_OFFER_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES sylius_admin_user (id) ON DELETE SET NULL, ADD CONSTRAINT FK_CN_OFFER_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES sylius_admin_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cardnext_quote_item ADD CONSTRAINT FK_CN_OFFER_ITEM_QUOTE FOREIGN KEY (quote_id) REFERENCES cardnext_quote (id) ON DELETE CASCADE, ADD CONSTRAINT FK_CN_OFFER_ITEM_PRODUCT FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE SET NULL, ADD CONSTRAINT FK_CN_OFFER_ITEM_VARIANT FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE cardnext_quote_item');
        $this->addSql('DROP TABLE cardnext_quote');
    }
}
