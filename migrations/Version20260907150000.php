<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;use Doctrine\Migrations\AbstractMigration;
final class Version20260907150000 extends AbstractMigration
{
 public function getDescription():string{return 'Assign one CMS homepage to each sales channel without deleting legacy homepage content';}
 public function up(Schema $schema):void{$this->addSql('ALTER TABLE sylius_channel ADD homepage_cms_page_id INT DEFAULT NULL');$this->addSql('ALTER TABLE sylius_channel ADD CONSTRAINT FK_CHANNEL_HOMEPAGE_CMS_PAGE FOREIGN KEY (homepage_cms_page_id) REFERENCES cardnext_cms_page (id) ON DELETE SET NULL');$this->addSql('CREATE INDEX IDX_CHANNEL_HOMEPAGE_CMS_PAGE ON sylius_channel (homepage_cms_page_id)');}
 public function down(Schema $schema):void{$this->addSql('ALTER TABLE sylius_channel DROP FOREIGN KEY FK_CHANNEL_HOMEPAGE_CMS_PAGE');$this->addSql('DROP INDEX IDX_CHANNEL_HOMEPAGE_CMS_PAGE ON sylius_channel');$this->addSql('ALTER TABLE sylius_channel DROP homepage_cms_page_id');}
}
