<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260716120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création du schéma portail et de la table portail.tile (tuiles du portail applicatif public)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS portail');
        $this->addSql('CREATE SEQUENCE portail.tile_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE portail.tile (
            id INT NOT NULL,
            titre VARCHAR(100) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            icone VARCHAR(100) DEFAULT NULL,
            image VARCHAR(255) DEFAULT NULL,
            url VARCHAR(255) NOT NULL,
            position INT NOT NULL,
            actif BOOLEAN NOT NULL,
            couleur VARCHAR(20) DEFAULT NULL,
            nouvel_onglet BOOLEAN NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN portail.tile.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN portail.tile.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE portail.tile');
        $this->addSql('DROP SEQUENCE portail.tile_id_seq CASCADE');
        $this->addSql('DROP SCHEMA IF EXISTS portail');
    }
}
