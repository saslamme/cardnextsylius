<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260830190000 extends AbstractMigration
{
 public function getDescription():string{return 'Move quote access to authenticated Sylius customer accounts';}
 public function up(Schema $schema):void
 {
  $this->addSql('ALTER TABLE cardnext_quote ADD customer_id INT DEFAULT NULL');
  $this->addSql("UPDATE cardnext_quote q INNER JOIN (SELECT LOWER(TRIM(email)) email_key, MIN(id) id FROM sylius_customer WHERE email IS NOT NULL AND TRIM(email) <> '' GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) = 1) c ON c.email_key = LOWER(TRIM(q.customer_email)) SET q.customer_id = c.id");
  $this->addSql('ALTER TABLE cardnext_quote ADD CONSTRAINT FK_CN_QUOTE_CUSTOMER FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE SET NULL');
  $this->addSql('CREATE INDEX IDX_CN_OFFER_CUSTOMER ON cardnext_quote (customer_id)');
  $this->addSql('ALTER TABLE cardnext_quote DROP access_token_hash, DROP access_token_issued_at');
 }
 public function down(Schema $schema):void
 {
  $this->addSql('ALTER TABLE cardnext_quote ADD access_token_hash VARCHAR(64) DEFAULT NULL, ADD access_token_issued_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
  $this->addSql('ALTER TABLE cardnext_quote DROP FOREIGN KEY FK_CN_QUOTE_CUSTOMER');
  $this->addSql('DROP INDEX IDX_CN_OFFER_CUSTOMER ON cardnext_quote');
  $this->addSql('ALTER TABLE cardnext_quote DROP customer_id');
 }
}
