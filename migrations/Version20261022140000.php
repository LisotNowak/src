<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Traçabilité : association des équipes aux utilisateurs (managers) — table vigne_equipe_user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vigne_equipe_user (equipe_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(equipe_id, user_id))');
        $this->addSql('CREATE INDEX IDX_C242C1A46D861B89 ON vigne_equipe_user (equipe_id)');
        $this->addSql('CREATE INDEX IDX_C242C1A4A76ED395 ON vigne_equipe_user (user_id)');
        $this->addSql('ALTER TABLE vigne_equipe_user ADD CONSTRAINT FK_C242C1A46D861B89 FOREIGN KEY (equipe_id) REFERENCES vigne_equipe (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE vigne_equipe_user ADD CONSTRAINT FK_C242C1A4A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vigne_equipe_user');
    }
}
