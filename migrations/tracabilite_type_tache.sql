-- =============================================================================
-- TRAÇABILITÉ VIGNE - Types de tâche
-- Une tâche appartient à un type (facultatif) ; un type est associé à une ou plusieurs équipes.
-- Base cible : symfony (PostgreSQL), schéma tracabilite
-- À exécuter AVANT de déployer le code correspondant.
-- Ajouts uniquement : aucune donnée existante n'est modifiée.
-- =============================================================================

BEGIN;

CREATE SEQUENCE tracabilite.vigne_type_tache_id_seq INCREMENT BY 1 MINVALUE 1 START 1;

CREATE TABLE tracabilite.vigne_type_tache (
    id  INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
);
CREATE UNIQUE INDEX UNIQ_6CB3CD2D6C6E55B5 ON tracabilite.vigne_type_tache (nom);

CREATE TABLE tracabilite.vigne_type_tache_equipe (
    type_tache_id INT NOT NULL,
    equipe_id     INT NOT NULL,
    PRIMARY KEY (type_tache_id, equipe_id)
);
CREATE INDEX IDX_F8F132DF1FDC7AC5 ON tracabilite.vigne_type_tache_equipe (type_tache_id);
CREATE INDEX IDX_F8F132DF6D861B89 ON tracabilite.vigne_type_tache_equipe (equipe_id);
ALTER TABLE tracabilite.vigne_type_tache_equipe
    ADD CONSTRAINT FK_F8F132DF1FDC7AC5 FOREIGN KEY (type_tache_id)
    REFERENCES tracabilite.vigne_type_tache (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
ALTER TABLE tracabilite.vigne_type_tache_equipe
    ADD CONSTRAINT FK_F8F132DF6D861B89 FOREIGN KEY (equipe_id)
    REFERENCES tracabilite.vigne_equipe (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE tracabilite.vigne_tache ADD type_id INT DEFAULT NULL;
ALTER TABLE tracabilite.vigne_tache
    ADD CONSTRAINT FK_DC72560AC54C8C93 FOREIGN KEY (type_id)
    REFERENCES tracabilite.vigne_type_tache (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE;
CREATE INDEX IDX_DC72560AC54C8C93 ON tracabilite.vigne_tache (type_id);

-- L'application se connecte avec ce rôle : utile si le script est lancé avec un autre compte (postgres...)
ALTER TABLE tracabilite.vigne_type_tache OWNER TO postgressymfony;
ALTER TABLE tracabilite.vigne_type_tache_equipe OWNER TO postgressymfony;
ALTER SEQUENCE tracabilite.vigne_type_tache_id_seq OWNER TO postgressymfony;

COMMIT;

-- Retour arrière (les associations saisies seraient perdues) :
-- BEGIN;
-- ALTER TABLE tracabilite.vigne_tache DROP COLUMN type_id;
-- DROP TABLE tracabilite.vigne_type_tache_equipe;
-- DROP TABLE tracabilite.vigne_type_tache;
-- DROP SEQUENCE tracabilite.vigne_type_tache_id_seq;
-- COMMIT;
