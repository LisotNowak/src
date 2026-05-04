<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260504081501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonne quantite dans dotation.demande_echange';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dotation.demande_echange ADD quantite INT DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dotation.demande_echange DROP COLUMN quantite');
    }
}
