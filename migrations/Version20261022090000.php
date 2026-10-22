<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Traçabilité : suppression de la notion de pause (heures_nettes, mode_pause, minutes_pause)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vigne_saisie DROP COLUMN heures_nettes');
        $this->addSql('ALTER TABLE vigne_saisie DROP COLUMN mode_pause');
        $this->addSql('ALTER TABLE vigne_saisie DROP COLUMN minutes_pause');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE vigne_saisie ADD mode_pause VARCHAR(20) DEFAULT 'auto' NOT NULL");
        $this->addSql('ALTER TABLE vigne_saisie ADD minutes_pause INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE vigne_saisie ADD heures_nettes DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE vigne_saisie SET heures_nettes = heures');
    }
}
