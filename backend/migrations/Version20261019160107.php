<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261019160107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Demandes de congé (spec 007)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE leave_request (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, decided_at DATETIME DEFAULT NULL, refusal_reason VARCHAR(1000) DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, cancellation_reason VARCHAR(1000) DEFAULT NULL, requester_id INTEGER NOT NULL, decided_by_id INTEGER DEFAULT NULL, cancelled_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_7DC8F778ED442CF4 FOREIGN KEY (requester_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7DC8F778E26B496B FOREIGN KEY (decided_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7DC8F778187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_7DC8F778ED442CF47B00651C ON leave_request (requester_id, status)');
        $this->addSql('CREATE INDEX IDX_7DC8F7787B00651C95275AB8 ON leave_request (status, start_date)');
        $this->addSql('CREATE INDEX IDX_7DC8F778ED442CF4 ON leave_request (requester_id)');
        $this->addSql('CREATE INDEX IDX_7DC8F778E26B496B ON leave_request (decided_by_id)');
        $this->addSql('CREATE INDEX IDX_7DC8F778187B2D12 ON leave_request (cancelled_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE leave_request');
    }
}
