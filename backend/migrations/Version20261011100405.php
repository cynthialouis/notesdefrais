<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011100405 extends AbstractMigration
{
    private const CATEGORIES = [
        'repas' => 'Repas',
        'transport' => 'Transport',
        'hebergement' => 'Hébergement',
        'materiel' => 'Matériel',
        'autre' => 'Autre',
    ];

    public function getDescription(): string
    {
        return 'Catégories de dépense, avec les 5 catégories de référence';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(30) NOT NULL, label VARCHAR(50) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_64C19C1989D9B62 ON category (slug)');

        // Données de référence : nécessaires dans tous les environnements, base de test comprise
        foreach (self::CATEGORIES as $slug => $label) {
            $this->addSql('INSERT INTO category (slug, label) VALUES (:slug, :label)', ['slug' => $slug, 'label' => $label]);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE category');
    }
}
