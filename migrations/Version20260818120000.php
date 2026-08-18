<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make configurators standalone and migrate localized catalog data from Sylius shell products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cardnext_configurator_translation (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, locale VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(512) NOT NULL, short_description LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description LONGTEXT DEFAULT NULL, UNIQUE INDEX uniq_configurator_locale (configurator_id, locale), UNIQUE INDEX uniq_configurator_path_locale (locale, path), INDEX IDX_CN_CFG_TRANSLATION_PARENT (configurator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_channel (configurator_id INT NOT NULL, channel_id INT NOT NULL, INDEX IDX_CN_CFG_CHANNEL_CONFIGURATOR (configurator_id), INDEX IDX_CN_CFG_CHANNEL_CHANNEL (channel_id), PRIMARY KEY(configurator_id, channel_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_image (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, path VARCHAR(512) NOT NULL, type VARCHAR(100) DEFAULT NULL, alt_text VARCHAR(255) DEFAULT NULL, position INT NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_CN_CFG_IMAGE_PARENT (configurator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cardnext_configurator_taxon (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, taxon_id INT NOT NULL, position INT NOT NULL, is_primary TINYINT(1) DEFAULT 0 NOT NULL, UNIQUE INDEX uniq_configurator_taxon (configurator_id, taxon_id), INDEX IDX_CN_CFG_TAXON_PARENT (configurator_id), INDEX IDX_CN_CFG_TAXON_TAXON (taxon_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cardnext_configurator_translation ADD CONSTRAINT FK_CN_CFG_TRANSLATION_PARENT FOREIGN KEY (configurator_id) REFERENCES cardnext_configurator (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_configurator_channel ADD CONSTRAINT FK_CN_CFG_CHANNEL_CONFIGURATOR FOREIGN KEY (configurator_id) REFERENCES cardnext_configurator (id) ON DELETE CASCADE, ADD CONSTRAINT FK_CN_CFG_CHANNEL_CHANNEL FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_configurator_image ADD CONSTRAINT FK_CN_CFG_IMAGE_PARENT FOREIGN KEY (configurator_id) REFERENCES cardnext_configurator (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cardnext_configurator_taxon ADD CONSTRAINT FK_CN_CFG_TAXON_PARENT FOREIGN KEY (configurator_id) REFERENCES cardnext_configurator (id) ON DELETE CASCADE, ADD CONSTRAINT FK_CN_CFG_TAXON_TAXON FOREIGN KEY (taxon_id) REFERENCES sylius_taxon (id) ON DELETE CASCADE');

        $this->addSql("INSERT INTO cardnext_configurator_translation (configurator_id, locale, name, path, short_description, description, meta_title, meta_description) SELECT c.id, t.locale, t.name, TRIM(BOTH '/' FROM t.configurator_path), t.short_description, t.description, t.name, t.meta_description FROM cardnext_configurator c INNER JOIN sylius_product_translation t ON t.translatable_id = c.product_id WHERE c.product_id IS NOT NULL AND t.configurator_path IS NOT NULL AND t.configurator_path <> ''");
        $this->addSql('INSERT IGNORE INTO cardnext_configurator_channel (configurator_id, channel_id) SELECT c.id, pc.channel_id FROM cardnext_configurator c INNER JOIN sylius_product_channels pc ON pc.product_id = c.product_id WHERE c.product_id IS NOT NULL');
        $this->addSql('INSERT INTO cardnext_configurator_image (configurator_id, path, type, alt_text, position, enabled) SELECT c.id, i.path, i.type, NULL, 0, 1 FROM cardnext_configurator c INNER JOIN sylius_product_image i ON i.owner_id = c.product_id WHERE c.product_id IS NOT NULL AND i.path IS NOT NULL');
        $this->addSql('INSERT IGNORE INTO cardnext_configurator_taxon (configurator_id, taxon_id, position, is_primary) SELECT c.id, pt.taxon_id, pt.position, 0 FROM cardnext_configurator c INNER JOIN sylius_product_taxon pt ON pt.product_id = c.product_id WHERE c.product_id IS NOT NULL');
        $this->addSql('UPDATE sylius_product p INNER JOIN cardnext_configurator c ON c.product_id = p.id SET p.enabled = 0 WHERE c.product_id IS NOT NULL');
        $this->addSql('ALTER TABLE cardnext_configurator DROP FOREIGN KEY FK_712BE502A8B7');
        $this->addSql('DROP INDEX UNIQ_CN_CONFIGURATOR_PRODUCT ON cardnext_configurator');
        $this->addSql('ALTER TABLE cardnext_configurator DROP product_id');
        $this->addSql('DROP INDEX UNIQ_CN_CONFIGURATOR_PATH_LOCALE ON sylius_product_translation');
        $this->addSql('ALTER TABLE sylius_product_translation DROP configurator_path');
        $this->addSql('ALTER TABLE sylius_product DROP product_kind');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sylius_product ADD product_kind VARCHAR(20) DEFAULT 'standard' NOT NULL");
        $this->addSql('ALTER TABLE sylius_product_translation ADD configurator_path VARCHAR(512) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CN_CONFIGURATOR_PATH_LOCALE ON sylius_product_translation (locale, configurator_path)');
        $this->addSql('ALTER TABLE cardnext_configurator ADD product_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CN_CONFIGURATOR_PRODUCT ON cardnext_configurator (product_id)');
        $this->addSql('ALTER TABLE cardnext_configurator ADD CONSTRAINT FK_712BE502A8B7 FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE RESTRICT');
        // Shell-product associations cannot be inferred unambiguously after administrators edit standalone configurators.
        $this->addSql('DROP TABLE cardnext_configurator_taxon');
        $this->addSql('DROP TABLE cardnext_configurator_image');
        $this->addSql('DROP TABLE cardnext_configurator_channel');
        $this->addSql('DROP TABLE cardnext_configurator_translation');
    }
}
