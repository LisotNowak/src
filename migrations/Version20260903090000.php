<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne inventaire.stock_article.a_traiter (case "à traiter plus tard")';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventaire.stock_article ADD a_traiter BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventaire.stock_article DROP COLUMN a_traiter');
    }
}
