<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260830140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Link an accepted Cardnext quote to its Sylius order'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_quote ADD order_id INT DEFAULT NULL, ADD converted_to_order_by_id INT DEFAULT NULL, ADD converted_to_order_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cardnext_quote ADD CONSTRAINT FK_CN_QUOTE_ORDER FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE SET NULL, ADD CONSTRAINT FK_CN_QUOTE_CONVERTER FOREIGN KEY (converted_to_order_by_id) REFERENCES sylius_admin_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CN_QUOTE_ORDER ON cardnext_quote (order_id)');
        $this->addSql('CREATE INDEX IDX_CN_QUOTE_CONVERTER ON cardnext_quote (converted_to_order_by_id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cardnext_quote DROP FOREIGN KEY FK_CN_QUOTE_ORDER, DROP FOREIGN KEY FK_CN_QUOTE_CONVERTER');
        $this->addSql('DROP INDEX UNIQ_CN_QUOTE_ORDER ON cardnext_quote');
        $this->addSql('DROP INDEX IDX_CN_QUOTE_CONVERTER ON cardnext_quote');
        $this->addSql('ALTER TABLE cardnext_quote DROP order_id, DROP converted_to_order_by_id, DROP converted_to_order_at');
    }
}
