<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add localized, nested public paths for configurable product landing pages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product_translation ADD configurator_path VARCHAR(512) DEFAULT NULL');
        $this->addSql("UPDATE sylius_product_translation translation INNER JOIN sylius_product product ON product.id = translation.translatable_id SET translation.configurator_path = CONCAT('konfigurator/', COALESCE(NULLIF(translation.slug, ''), product.code), '-', product.id) WHERE product.product_kind = 'configurable' AND translation.configurator_path IS NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CN_CONFIGURATOR_PATH_LOCALE ON sylius_product_translation (locale, configurator_path)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CN_CONFIGURATOR_PATH_LOCALE ON sylius_product_translation');
        $this->addSql('ALTER TABLE sylius_product_translation DROP configurator_path');
    }
}
