<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260917090000 extends AbstractMigration
{
 public function getDescription(): string { return 'Add product expert profiles and assortments'; }
 public function up(Schema $schema): void { $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on MySQL.'); $this->addSql('CREATE TABLE cardnext_product_expert (id INT AUTO_INCREMENT NOT NULL, admin_user_id INT NOT NULL, channel_id INT NOT NULL, display_name VARCHAR(255) NOT NULL, slug VARCHAR(150) NOT NULL, intro_text LONGTEXT DEFAULT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_EXPERT_ADMIN (admin_user_id), UNIQUE INDEX uniq_product_expert_channel_slug (channel_id, slug), INDEX IDX_EXPERT_CHANNEL (channel_id), PRIMARY KEY(id), CONSTRAINT FK_EXPERT_ADMIN FOREIGN KEY (admin_user_id) REFERENCES sylius_admin_user (id) ON DELETE CASCADE, CONSTRAINT FK_EXPERT_CHANNEL FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'); $this->addSql('CREATE TABLE cardnext_product_expert_product (id INT AUTO_INCREMENT NOT NULL, expert_id INT NOT NULL, product_id INT NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_expert_product (expert_id, product_id), INDEX idx_expert_position (expert_id, position), INDEX IDX_EXPERT_PRODUCT_PRODUCT (product_id), PRIMARY KEY(id), CONSTRAINT FK_EXPERT_PRODUCT_EXPERT FOREIGN KEY (expert_id) REFERENCES cardnext_product_expert (id) ON DELETE CASCADE, CONSTRAINT FK_EXPERT_PRODUCT_PRODUCT FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'); }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE cardnext_product_expert_product'); $this->addSql('DROP TABLE cardnext_product_expert'); }
}
