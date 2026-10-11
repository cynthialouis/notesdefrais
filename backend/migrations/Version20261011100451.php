<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011100451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notes de frais';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(80) NOT NULL, amount_cents INTEGER NOT NULL, date DATE NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, submitted_at DATETIME DEFAULT NULL, owner_id INTEGER NOT NULL, category_id INTEGER NOT NULL, CONSTRAINT FK_2D3A8DA67E3C61F9 FOREIGN KEY (owner_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F97B00651C ON expense (owner_id, status)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67E3C61F9 ON expense (owner_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA612469DE2 ON expense (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE expense');
    }
}
