<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store immutable publicly shareable configurator snapshots'; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.');
        $this->addSql("CREATE TABLE cardnext_saved_configurator_configuration (id INT AUTO_INCREMENT NOT NULL, configurator_id INT NOT NULL, channel_id INT NOT NULL, token VARCHAR(64) NOT NULL, locale_code VARCHAR(16) NOT NULL, quantity INT NOT NULL, selections JSON NOT NULL, lead_time_code VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_CN_SAVED_TOKEN (token), INDEX IDX_CN_SAVED_CONFIGURATOR (configurator_id), INDEX IDX_CN_SAVED_CHANNEL (channel_id), INDEX IDX_CN_SAVED_CREATED_AT (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE cardnext_saved_configurator_configuration ADD CONSTRAINT FK_CN_SAVED_CONFIGURATOR FOREIGN KEY (configurator_id) REFERENCES cardnext_configurator (id) ON DELETE CASCADE, ADD CONSTRAINT FK_CN_SAVED_CHANNEL FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE cardnext_saved_configurator_configuration'); }
}
