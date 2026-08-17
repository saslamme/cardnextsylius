<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the generic Cardnext configurator core schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cardnext_configurator (id INT AUTO_INCREMENT NOT NULL, product_id INT DEFAULT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_CONFIGURATOR_CODE (code), INDEX IDX_CN_CONFIGURATOR_PRODUCT (product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE cardnext_configurator_section (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, position INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_CN_CFG_SECTION_CODE (configurator_id, code), INDEX IDX_CN_CFG_SECTION_PARENT (configurator_id, position, enabled), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_field (id INT AUTO_INCREMENT NOT NULL, section_id INT NOT NULL, configurator_id INT NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, help_text LONGTEXT DEFAULT NULL, type VARCHAR(30) NOT NULL, required TINYINT(1) DEFAULT 0 NOT NULL, position INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, minimum_value VARCHAR(64) DEFAULT NULL, maximum_value VARCHAR(64) DEFAULT NULL, step VARCHAR(64) DEFAULT NULL, UNIQUE INDEX UNIQ_CN_CFG_FIELD_CODE (configurator_id, code), INDEX IDX_CN_CFG_FIELD_CONFIGURATOR (configurator_id), INDEX IDX_CN_CFG_FIELD_PARENT (section_id, position, enabled), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_value (id INT AUTO_INCREMENT NOT NULL, field_id INT NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, position INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, color_hex VARCHAR(7) DEFAULT NULL, image_path VARCHAR(500) DEFAULT NULL, icon VARCHAR(100) DEFAULT NULL, UNIQUE INDEX UNIQ_CN_CFG_VALUE_CODE (field_id, code), INDEX IDX_CN_CFG_VALUE_PARENT (field_id, position, enabled), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("CREATE TABLE cardnext_configurator_price_rule (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, value_id INT DEFAULT NULL, channel_id INT DEFAULT NULL, multiplier_field_id INT DEFAULT NULL, currency_code VARCHAR(3) NOT NULL, charge_code VARCHAR(100) NOT NULL, label VARCHAR(255) DEFAULT NULL, minimum_quantity INT NOT NULL, maximum_quantity INT DEFAULT NULL, price_type VARCHAR(20) NOT NULL, amount BIGINT NOT NULL, multiplier_type VARCHAR(20) NOT NULL, percentage_base VARCHAR(20) DEFAULT NULL, priority INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_CN_CFG_RULE_LOOKUP (configurator_id, value_id, channel_id, currency_code, minimum_quantity, maximum_quantity, enabled), INDEX IDX_CN_CFG_RULE_VALUE (value_id), INDEX IDX_CN_CFG_RULE_CHANNEL (channel_id), INDEX IDX_CN_CFG_RULE_MULTIPLIER (multiplier_field_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE cardnext_configurator_dependency (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, source_field_id INT NOT NULL, target_field_id INT DEFAULT NULL, target_value_id INT DEFAULT NULL, operator VARCHAR(40) NOT NULL, expected_values JSON NOT NULL, effect VARCHAR(20) NOT NULL, priority INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CN_CFG_DEP_LOOKUP (configurator_id, enabled, priority), INDEX IDX_CN_CFG_DEP_SOURCE (source_field_id), INDEX IDX_CN_CFG_DEP_TARGET_FIELD (target_field_id), INDEX IDX_CN_CFG_DEP_TARGET_VALUE (target_value_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE cardnext_configurator_lead_time (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, working_days INT NOT NULL, position INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_CN_CFG_LEAD_CODE (configurator_id, code), INDEX IDX_CN_CFG_LEAD_PARENT (configurator_id, position, enabled), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        foreach (['cardnext_configurator' => 'product_id,sylius_product,id,SET NULL','cardnext_configurator_section' => 'configurator_id,cardnext_configurator,id,CASCADE','cardnext_configurator_field' => 'section_id,cardnext_configurator_section,id,CASCADE|configurator_id,cardnext_configurator,id,CASCADE','cardnext_configurator_value' => 'field_id,cardnext_configurator_field,id,CASCADE','cardnext_configurator_price_rule' => 'configurator_id,cardnext_configurator,id,CASCADE|value_id,cardnext_configurator_value,id,CASCADE|channel_id,sylius_channel,id,CASCADE|multiplier_field_id,cardnext_configurator_field,id,CASCADE','cardnext_configurator_dependency' => 'configurator_id,cardnext_configurator,id,CASCADE|source_field_id,cardnext_configurator_field,id,CASCADE|target_field_id,cardnext_configurator_field,id,CASCADE|target_value_id,cardnext_configurator_value,id,CASCADE','cardnext_configurator_lead_time' => 'configurator_id,cardnext_configurator,id,CASCADE'] as $table => $defs) {
            foreach (explode('|', $defs) as $def) {
                [$col,$target,$targetCol,$delete] = explode(',', $def);
                $this->addSql("ALTER TABLE $table ADD CONSTRAINT FK_".strtoupper(substr(sha1($table.$col), 0, 12))." FOREIGN KEY ($col) REFERENCES $target ($targetCol) ON DELETE $delete");
            }
        }
    }
    public function down(Schema $schema): void
    {
        foreach (['cardnext_configurator_dependency','cardnext_configurator_price_rule','cardnext_configurator_lead_time','cardnext_configurator_value','cardnext_configurator_field','cardnext_configurator_section','cardnext_configurator'] as $table) {
            $this->addSql("DROP TABLE $table");
        }
    }
}
