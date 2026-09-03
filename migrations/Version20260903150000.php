<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add channel-specific public variant quantity tier prices'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cardnext_variant_tier_price (id INT AUTO_INCREMENT NOT NULL, variant_id INT NOT NULL, channel_code VARCHAR(255) NOT NULL, min_quantity INT NOT NULL, price INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_VARIANT_TIER (variant_id, channel_code, min_quantity), INDEX IDX_CN_VARIANT_TIER_LOOKUP (variant_id, channel_code, min_quantity), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cardnext_variant_tier_price ADD CONSTRAINT FK_CN_VARIANT_TIER_VARIANT FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE');
        // Preserve already configured public rules while separating them from group pricing.
        $this->addSql("INSERT INTO cardnext_variant_tier_price (variant_id, channel_code, min_quantity, price, created_at, updated_at) SELECT variant_id, channel_code, min_quantity, price, created_at, COALESCE(updated_at, created_at) FROM cardnext_variant_price_rule WHERE customer_group_code = '' AND enabled = 1");
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE cardnext_variant_tier_price'); }
}
