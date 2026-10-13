<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013055339 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Justificatif d\'une note (spec 005)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE receipt (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, uploaded_at DATETIME NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(50) NOT NULL, size_bytes INTEGER NOT NULL, storage_name VARCHAR(64) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5399B645570EB513 ON receipt (storage_name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__expense AS SELECT id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id, decided_at, rejection_reason, decided_by_id, limit_at_approval_cents FROM expense');
        $this->addSql('DROP TABLE expense');
        $this->addSql('CREATE TABLE expense (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(80) NOT NULL, amount_cents INTEGER NOT NULL, date DATE NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, submitted_at DATETIME DEFAULT NULL, owner_id INTEGER NOT NULL, category_id INTEGER NOT NULL, decided_at DATETIME DEFAULT NULL, rejection_reason VARCHAR(1000) DEFAULT NULL, decided_by_id INTEGER DEFAULT NULL, limit_at_approval_cents INTEGER DEFAULT NULL, receipt_id INTEGER DEFAULT NULL, CONSTRAINT FK_2D3A8DA67E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA6E26B496B FOREIGN KEY (decided_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA62B5CA896 FOREIGN KEY (receipt_id) REFERENCES receipt (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO expense (id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id, decided_at, rejection_reason, decided_by_id, limit_at_approval_cents) SELECT id, label, amount_cents, date, status, created_at, submitted_at, owner_id, category_id, decided_at, rejection_reason, decided_by_id, limit_at_approval_cents FROM __temp__expense');
        $this->addSql('DROP TABLE __temp__expense');
        $this->addSql('CREATE INDEX IDX_2D3A8DA6E26B496B ON expense (decided_by_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F97B00651C ON expense (owner_id, status)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F9 ON expense (owner_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA612469DE2 ON expense (category_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2D3A8DA62B5CA896 ON expense (receipt_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE receipt');
        $this->addSql('CREATE TEMPORARY TABLE __temp__expense AS SELECT id, label, amount_cents, date, status, created_at, submitted_at, decided_at, rejection_reason, limit_at_approval_cents, owner_id, category_id, decided_by_id FROM expense');
        $this->addSql('DROP TABLE expense');
        $this->addSql('CREATE TABLE expense (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(80) NOT NULL, amount_cents INTEGER NOT NULL, date DATE NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, submitted_at DATETIME DEFAULT NULL, decided_at DATETIME DEFAULT NULL, rejection_reason VARCHAR(1000) DEFAULT NULL, limit_at_approval_cents INTEGER DEFAULT NULL, owner_id INTEGER NOT NULL, category_id INTEGER NOT NULL, decided_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_2D3A8DA67E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA6E26B496B FOREIGN KEY (decided_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO expense (id, label, amount_cents, date, status, created_at, submitted_at, decided_at, rejection_reason, limit_at_approval_cents, owner_id, category_id, decided_by_id) SELECT id, label, amount_cents, date, status, created_at, submitted_at, decided_at, rejection_reason, limit_at_approval_cents, owner_id, category_id, decided_by_id FROM __temp__expense');
        $this->addSql('DROP TABLE __temp__expense');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F97B00651C ON expense (owner_id, status)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F9 ON expense (owner_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA612469DE2 ON expense (category_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA6E26B496B ON expense (decided_by_id)');
    }
}
