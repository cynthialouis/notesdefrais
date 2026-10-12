<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012133552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Politique de dépense par catégorie et plafond figé à la validation';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category ADD COLUMN max_amount_cents INTEGER DEFAULT NULL');
        $this->addSql('ALTER TABLE category ADD COLUMN receipt_required BOOLEAN DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE expense ADD COLUMN limit_at_approval_cents INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__category AS SELECT id, slug, label FROM category');
        $this->addSql('DROP TABLE category');
        $this->addSql('CREATE TABLE category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(30) NOT NULL, label VARCHAR(50) NOT NULL)');
        $this->addSql('INSERT INTO category (id, slug, label) SELECT id, slug, label FROM __temp__category');
        $this->addSql('DROP TABLE __temp__category');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C1989D9B62 ON category (slug)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__expense AS SELECT id, label, amount_cents, date, status, created_at, submitted_at, decided_at, rejection_reason, owner_id, category_id, decided_by_id FROM expense');
        $this->addSql('DROP TABLE expense');
        $this->addSql('CREATE TABLE expense (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(80) NOT NULL, amount_cents INTEGER NOT NULL, date DATE NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, submitted_at DATETIME DEFAULT NULL, decided_at DATETIME DEFAULT NULL, rejection_reason VARCHAR(1000) DEFAULT NULL, owner_id INTEGER NOT NULL, category_id INTEGER NOT NULL, decided_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_2D3A8DA67E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA6E26B496B FOREIGN KEY (decided_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO expense (id, label, amount_cents, date, status, created_at, submitted_at, decided_at, rejection_reason, owner_id, category_id, decided_by_id) SELECT id, label, amount_cents, date, status, created_at, submitted_at, decided_at, rejection_reason, owner_id, category_id, decided_by_id FROM __temp__expense');
        $this->addSql('DROP TABLE __temp__expense');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F97B00651C ON expense (owner_id, status)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F9 ON expense (owner_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA612469DE2 ON expense (category_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA6E26B496B ON expense (decided_by_id)');
    }
}
