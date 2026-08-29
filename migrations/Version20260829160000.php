<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260829160000 extends AbstractMigration
{
 public function getDescription(): string{return 'Add quote public delivery and decision fields';}
 public function up(Schema $schema):void{$this->addSql('ALTER TABLE cardnext_quote ADD access_token_hash VARCHAR(64) DEFAULT NULL, ADD access_token_issued_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD first_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD last_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD send_count INT DEFAULT 0 NOT NULL, ADD first_viewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD last_viewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD view_count INT DEFAULT 0 NOT NULL, ADD accepted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD accepted_by_name VARCHAR(255) DEFAULT NULL, ADD rejected_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD rejected_by_name VARCHAR(255) DEFAULT NULL, ADD rejection_reason LONGTEXT DEFAULT NULL');}
 public function down(Schema $schema):void{$this->addSql('ALTER TABLE cardnext_quote DROP access_token_hash, DROP access_token_issued_at, DROP first_sent_at, DROP last_sent_at, DROP send_count, DROP first_viewed_at, DROP last_viewed_at, DROP view_count, DROP accepted_at, DROP accepted_by_name, DROP rejected_at, DROP rejected_by_name, DROP rejection_reason');}
}
