<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012130344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Décision sur une note (auteur, date, motif) et historique des actions';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(255) NOT NULL, comment VARCHAR(1000) DEFAULT NULL, expense_id INTEGER NOT NULL, actor_id INTEGER NOT NULL, CONSTRAINT FK_98A6AD9DF395DB7B FOREIGN KEY (expense_id) REFERENCES expense (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_98A6AD9D10DAF24A FOREIGN KEY (actor_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_98A6AD9DF395DB7B8B8E8428 ON expense_event (expense_id, created_at)');
        $this->addSql('CREATE INDEX IDX_98A6AD9DF395DB7B ON expense_event (expense_id)');
        $this->addSql('CREATE INDEX IDX_98A6AD9D10DAF24A ON expense_event (actor_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__expense AS SELECT id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id FROM expense');
        $this->addSql('DROP TABLE expense');
        $this->addSql('CREATE TABLE expense (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(80) NOT NULL, amount_cents INTEGER NOT NULL, date DATE NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, submitted_at DATETIME DEFAULT NULL, owner_id INTEGER NOT NULL, category_id INTEGER NOT NULL, decided_at DATETIME DEFAULT NULL, rejection_reason VARCHAR(1000) DEFAULT NULL, decided_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_2D3A8DA67E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA6E26B496B FOREIGN KEY (decided_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO expense (id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id) SELECT id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id FROM __temp__expense');
        $this->addSql('DROP TABLE __temp__expense');
        $this->addSql('CREATE INDEX IDX_2D3A8DA612469DE2 ON expense (category_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F9 ON expense (owner_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F97B00651C ON expense (owner_id, status)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA6E26B496B ON expense (decided_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE expense_event');
        $this->addSql('CREATE TEMPORARY TABLE __temp__expense AS SELECT id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id FROM expense');
        $this->addSql('DROP TABLE expense');
        $this->addSql('CREATE TABLE expense (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(80) NOT NULL, amount_cents INTEGER NOT NULL, date DATE NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, submitted_at DATETIME DEFAULT NULL, owner_id INTEGER NOT NULL, category_id INTEGER NOT NULL, CONSTRAINT FK_2D3A8DA67E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO expense (id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id) SELECT id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id FROM __temp__expense');
        $this->addSql('DROP TABLE __temp__expense');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F97B00651C ON expense (owner_id, status)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F9 ON expense (owner_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA612469DE2 ON expense (category_id)');
    }
}
