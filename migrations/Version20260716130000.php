<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des catégories du portail (portail.category) et rattachement des tuiles (portail.tile.category_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE portail.category_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE portail.category (
            id INT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            icone VARCHAR(100) DEFAULT NULL,
            couleur VARCHAR(20) DEFAULT NULL,
            position INT NOT NULL,
            actif BOOLEAN NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql("INSERT INTO portail.category (id, nom, icone, couleur, position, actif) VALUES (nextval('portail.category_id_seq'), 'Général', 'fas fa-star', '#6c757d', 0, true)");
        $this->addSql("INSERT INTO portail.category (id, nom, icone, couleur, position, actif) VALUES (nextval('portail.category_id_seq'), 'Informatique', 'fas fa-laptop-code', '#0d6efd', 1, true)");
        $this->addSql("INSERT INTO portail.category (id, nom, icone, couleur, position, actif) VALUES (nextval('portail.category_id_seq'), 'Vigne', 'fas fa-leaf', '#198754', 2, true)");

        $this->addSql('ALTER TABLE portail.tile ADD category_id INT DEFAULT NULL');
        $this->addSql("UPDATE portail.tile SET category_id = (SELECT id FROM portail.category WHERE nom = 'Général') WHERE category_id IS NULL");
        $this->addSql('ALTER TABLE portail.tile ALTER COLUMN category_id SET NOT NULL');
        $this->addSql('ALTER TABLE portail.tile ADD CONSTRAINT FK_portail_tile_category FOREIGN KEY (category_id) REFERENCES portail.category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_portail_tile_category ON portail.tile (category_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portail.tile DROP CONSTRAINT FK_portail_tile_category');
        $this->addSql('DROP INDEX portail.IDX_portail_tile_category');
        $this->addSql('ALTER TABLE portail.tile DROP COLUMN category_id');
        $this->addSql('DROP TABLE portail.category');
        $this->addSql('DROP SEQUENCE portail.category_id_seq CASCADE');
    }
}
