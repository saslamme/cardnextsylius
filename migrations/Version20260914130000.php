<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260914130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the redundant variant tier price lookup index';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.');

        $this->addSql('DROP INDEX IDX_CN_VARIANT_TIER_LOOKUP ON cardnext_variant_tier_price');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.');

        $this->addSql('CREATE INDEX IDX_CN_VARIANT_TIER_LOOKUP ON cardnext_variant_tier_price (variant_id, channel_code, min_quantity)');
    }
}
