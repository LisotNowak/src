-- =============================================================================
-- TRAÇABILITÉ VIGNE - Script d'initialisation PostgreSQL
-- Généré depuis la maquette HTML Tracabilite_chef_secteurs v2
-- Base cible : symfony (PostgreSQL 17)
-- Encodage : UTF-8
-- =============================================================================

BEGIN;

-- =============================================================================
-- 1. CRÉATION DES TABLES
-- =============================================================================

-- Équipes / Chefs de secteur
CREATE TABLE IF NOT EXISTS vigne_equipe (
    id      SERIAL PRIMARY KEY,
    nom     VARCHAR(100) NOT NULL,
    CONSTRAINT uq_equipe_nom UNIQUE (nom)
);

-- Tâches viticoles et RH
CREATE TABLE IF NOT EXISTS vigne_tache (
    id           SERIAL PRIMARY KEY,
    nom          VARCHAR(255) NOT NULL,
    sans_parcel  BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT uq_tache_nom UNIQUE (nom)
);

-- Parcelles viticoles
CREATE TABLE IF NOT EXISTS vigne_parcelle (
    id            SERIAL PRIMARY KEY,
    slug          VARCHAR(255) NOT NULL,     -- identifiant technique (ex: artigues_centre_1)
    nombre_pieds  INTEGER,                   -- nombre total de pieds plantés
    surface       NUMERIC(6,2),              -- surface en hectares
    cepage        VARCHAR(100),              -- cépage principal (CS, M, PV…)
    gamme         VARCHAR(50),               -- gamme (FL, GV, PA…)
    geometrie     JSONB,                     -- GeoJSON MultiPolygon, Lambert 93 (EPSG:2154)
    CONSTRAINT uq_parcelle_slug UNIQUE (slug)
);

-- Personnel viticole
CREATE TABLE IF NOT EXISTS vigne_ouvrier (
    id           SERIAL PRIMARY KEY,
    nom_complet  VARCHAR(255) NOT NULL,
    equipe_id    INTEGER REFERENCES vigne_equipe(id) ON DELETE SET NULL,
    contrat      VARCHAR(50)  NOT NULL DEFAULT 'Permanent',  -- Permanent | CDD | Saisonnier
    CONSTRAINT uq_ouvrier_nom UNIQUE (nom_complet)
);
CREATE INDEX IF NOT EXISTS idx_ouvrier_equipe ON vigne_ouvrier(equipe_id);

-- Saisies de traçabilité terrain et RH
CREATE TABLE IF NOT EXISTS vigne_saisie (
    id                VARCHAR(36)    PRIMARY KEY,           -- UUID applicatif
    date_travail      DATE           NOT NULL,
    mois              VARCHAR(7)     NOT NULL,              -- YYYY-MM
    chef_nom          VARCHAR(100)   NOT NULL,
    personnel_nom     VARCHAR(255)   NOT NULL,
    personnel_contrat VARCHAR(50),
    tache_nom         VARCHAR(255)   NOT NULL,
    parcelle_nom      VARCHAR(255),                        -- NULL pour tâches RH
    heures            NUMERIC(8,2)   NOT NULL DEFAULT 0,
    heures_nettes     NUMERIC(8,2)   NOT NULL DEFAULT 0,   -- heures - pause
    effectif          INTEGER        NOT NULL DEFAULT 1,
    mode_pause        VARCHAR(20)    NOT NULL DEFAULT 'auto',
    minutes_pause     INTEGER        NOT NULL DEFAULT 0,
    avancement        NUMERIC(5,2)   NOT NULL DEFAULT 0,   -- 0 à 100 %
    pieds             NUMERIC(10,2)  NOT NULL DEFAULT 0,
    pieds_total       NUMERIC(10,2)  NOT NULL DEFAULT 0,
    commentaire       TEXT,
    type              VARCHAR(20)    NOT NULL DEFAULT 'Terrain', -- Terrain | RH
    cree_a            TIMESTAMP      NOT NULL DEFAULT NOW(),
    modifie_a         TIMESTAMP,
    nombre_modifs     INTEGER        NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_saisie_mois       ON vigne_saisie(mois);
CREATE INDEX IF NOT EXISTS idx_saisie_chef       ON vigne_saisie(chef_nom);
CREATE INDEX IF NOT EXISTS idx_saisie_personnel  ON vigne_saisie(personnel_nom);
CREATE INDEX IF NOT EXISTS idx_saisie_parcelle   ON vigne_saisie(parcelle_nom);
CREATE INDEX IF NOT EXISTS idx_saisie_date       ON vigne_saisie(date_travail);

-- Journal d'audit
CREATE TABLE IF NOT EXISTS vigne_journal (
    id             VARCHAR(36)   PRIMARY KEY,
    action         VARCHAR(50)   NOT NULL,          -- Création | Modification | Suppression
    note           VARCHAR(500),
    effectue_a     TIMESTAMP     NOT NULL DEFAULT NOW(),
    saisie_id      VARCHAR(36)   NOT NULL,
    saisie_date    VARCHAR(10),
    chef_nom       VARCHAR(100),
    personnel_nom  VARCHAR(255),
    tache_nom      VARCHAR(255),
    parcelle_nom   VARCHAR(255),
    avant          TEXT,                            -- JSON snapshot avant
    apres          TEXT                             -- JSON snapshot après
);
CREATE INDEX IF NOT EXISTS idx_journal_saisie ON vigne_journal(saisie_id);
CREATE INDEX IF NOT EXISTS idx_journal_date   ON vigne_journal(effectue_a);


-- =============================================================================
-- 2. ÉQUIPES
-- =============================================================================

INSERT INTO vigne_equipe (nom) VALUES
    ('Carol'),
    ('Michèle'),
    ('Thierry')
ON CONFLICT (nom) DO NOTHING;


-- =============================================================================
-- 3. TÂCHES (60 tâches, dont 12 sans parcelle)
-- =============================================================================

INSERT INTO vigne_tache (nom, sans_parcel) VALUES
    ('Absence',                                    TRUE),
    ('Accident de travail',                        TRUE),
    ('Américains',                                 FALSE),
    ('Arrêt Maladie',                              TRUE),
    ('Arrosage',                                   FALSE),
    ('Attachage plants',                           FALSE),
    ('Baisser fils + agrafes',                     FALSE),
    ('Broustes',                                   FALSE),
    ('Chef d''équipe',                             FALSE),
    ('Cisaille bouts',                             FALSE),
    ('Complantation alignement',                   FALSE),
    ('Complantation plant',                        FALSE),
    ('Confusion sexuelle',                         FALSE),
    ('Congés paternité/maternité',                 TRUE),
    ('Congés payés',                               TRUE),
    ('Congés pour autre motif',                    TRUE),
    ('Congés sans solde',                          TRUE),
    ('Coupage des lies',                           FALSE),
    ('Coupage des liens',                          FALSE),
    ('Couper mannes',                              FALSE),
    ('Couper grandes branches',                    FALSE),
    ('Déchaussage manuel',                         FALSE),
    ('Dégager branches du bout',                   FALSE),
    ('Désherbage manuel',                          FALSE),
    ('Dessécaillage / Garnissage / Maillage',      FALSE),
    ('Détressage',                                 FALSE),
    ('Divers',                                     FALSE),
    ('Échardage',                                  FALSE),
    ('Eclaircissage',                              FALSE),
    ('École',                                      TRUE),
    ('Effeuillage',                                FALSE),
    ('Entretien axes principaux',                  FALSE),
    ('Épamprage 1 / Dédobulage',                   FALSE),
    ('Épamprage 2 / Dédobulage',                   FALSE),
    ('Formation professionnelle',                  TRUE),
    ('Jardins',                                    FALSE),
    ('Marquage des lies',                          FALSE),
    ('Marquage des pieds',                         FALSE),
    ('Nettoyage batiments',                        FALSE),
    ('Nettoyage de fonds',                         FALSE),
    ('Nettoyage du vignoble',                      FALSE),
    ('Nettoyage grappes',                          FALSE),
    ('Nettoyage matériel',                         FALSE),
    ('Pieds morts',                                FALSE),
    ('Pliage / Acanage',                           FALSE),
    ('Récupération',                               TRUE),
    ('Relevage 1 / attachage',                     FALSE),
    ('Relevage 2 / attachage',                     FALSE),
    ('Réparation des bouts',                       FALSE),
    ('Réparation des fils',                        FALSE),
    ('Réunion',                                    TRUE),
    ('Rotofil',                                    FALSE),
    ('Taille',                                     FALSE),
    ('Tarière',                                    FALSE),
    ('Tirer cavaillons',                           FALSE),
    ('Tirer les bouts',                            FALSE),
    ('Tressage',                                   FALSE),
    ('Tutorat',                                    FALSE),
    ('Vendanges',                                  FALSE),
    ('Visite médicale',                            TRUE)
ON CONFLICT (nom) DO UPDATE SET sans_parcel = EXCLUDED.sans_parcel;


-- =============================================================================
-- 4. PARCELLES
-- Colonnes : slug, nombre_pieds, surface (ha), cepage, gamme
-- La colonne geometrie sera alimentée séparément (cf. section 4b).
-- =============================================================================

INSERT INTO vigne_parcelle (slug, nombre_pieds, surface, cepage, gamme) VALUES
    ('artigues_centre_1',           578,   0.06, 'CS',         'FL'),
    ('artigues_centre_2',           749,   0.08, 'CS',         'FL'),
    ('artigues_centre_3',           576,   0.06, 'M',          'FL'),
    ('artigues_centre_4',          5386,   0.80, 'CS',         'PA'),
    ('artigues_centre_5',          1454,   0.16, 'CS',         'PA'),
    ('artigues_la_gravette',       1523,   0.17, 'CS',         'PA'),
    ('artigues_le_bois_1',         3624,   0.41, 'CS',         'FL'),
    ('artigues_le_bois_2',         4413,   0.51, 'M',          'FL'),
    ('artigues_le_bois_3',         2967,   0.36, 'CS',         'FL'),
    ('artigues_le_bois_4',         3186,   0.37, 'CS',         'FL'),
    ('artigues_le_bois_5',         1238,   0.14, 'CS',         'PA'),
    ('artigues_le_bois_6',         5173,   0.54, 'CS',         'FL'),
    ('artigues_les_chevres_1',     2380,   0.21, 'M',          'FL'),
    ('artigues_les_chevres_2',     4455,   0.49, 'M',          'FL'),
    ('artigues_les_chevres_3',     3203,   0.34, 'CS',         'FL'),
    ('artigues_planteyrot_1',       162,   0.02, 'M',          'FL'),
    ('artigues_planteyrot_2',       211,   0.02, 'M',          'FL'),
    ('artigues_planteyrot_3',      1470,   0.16, 'CS',         'FL'),
    ('artigues_roseaux',           2154,   0.23, 'M',          'FL'),
    ('artigues_triau_bas_2',       1553,   0.17, 'M',          'PA'),
    ('artigues_triau_haut_1',      3901,   0.42, 'M',          'PA'),
    ('artigues_virage',            3711,   0.51, 'CS',         'PA'),
    ('berret_1',                   7948,   0.83, 'M',          'PA'),
    ('berret_2',                   6943,   0.74, 'M',          'PA'),
    ('berret_3',                   3836,   0.22, 'M',          'PA'),
    ('bois_de_la_tour',            5882,   0.59, 'M',          'FL'),
    ('canaires',                   3198,   0.37, 'CS',         'GV'),
    ('canterane',                 14380,   1.53, 'M',          'FL'),
    ('chais_1',                    4132,   0.47, 'CS',         'GV'),
    ('chais_2',                    3557,   0.41, 'CS',         'GV'),
    ('chataigniers',               3332,   0.34, 'CS',         'GV'),
    ('chene_vert_1',               2123,   0.21, 'CS',         'GV'),
    ('chene_vert_2',              10094,   1.03, 'CS',         'GV'),
    ('chene_vert_3',              17088,   1.75, 'CS',         'GV'),
    ('dardenans_1',                3575,   0.39, 'M',          'PA'),
    ('dardenans_2',                9485,   1.04, 'CS',         'PA'),
    ('dardenans_3',                3947,   0.43, 'CS',         'PA'),
    ('fiouzaille',                  944,   0.10, 'CS',         'FL'),
    ('font_de_jeanne_CS',          9416,   1.22, 'Plantation', NULL),
    ('font_de_jeanne_M',           2823,   0.30, 'M',          'FL'),
    ('font_de_jeanne_PV',          5159,   0.69, 'PV',         'FL'),
    ('forts_de_latour_CS_nord',    7582,   0.77, 'CS',         'GV'),
    ('forts_de_latour_CS_sud',     2430,   0.25, 'CS',         'GV'),
    ('forts_de_latour_M',          4764,   0.49, 'M',          'FL'),
    ('forts_de_latour_PV',          294,   0.03, 'PV',         'GV'),
    ('gaillottes_1',               2534,   0.90, 'CS',         'FL'),
    ('gaillottes_2',                836,   0.09, 'M',          'FL'),
    ('gaillottes_3',               3042,   0.33, 'CS',         'FL'),
    ('gaillottes_4',                357,   0.04, 'M',          'FL'),
    ('gaillottes_5',               2278,   0.25, 'CS',         'FL'),
    ('gaillottes_6',                502,   0.06, 'M',          'FL'),
    ('gaillottes_7',               4343,   0.48, 'CS',         'FL'),
    ('garennes_1',                 3798,   0.39, 'CS',         'GV'),
    ('garennes_2',                 4257,   0.43, 'CS',         'GV'),
    ('grand_enclos',              17912,   2.05, 'CS',         'GV'),
    ('grand_faure_1',             14132,   1.60, 'CS',         'GV'),
    ('grand_faure_2',             16328,   2.06, 'Plantation', NULL),
    ('grand_sablonnet_1',         14950,   1.62, 'CS',         'FL'),
    ('grand_sablonnet_2_collection', 10721, 1.33, 'CS',        'FL'),
    ('grand_sablonnet_2_sud',      3118,   0.33, 'CS',         'FL'),
    ('grande_lande_1',             2120,   0.23, 'M',          'FL'),
    ('grande_lande_2',             1569,   0.19, 'CS',         'FL'),
    ('grande_lande_3',              460,   NULL, 'CS',         NULL),
    ('gravette',                  11468,   1.13, 'CS',         'GV'),
    ('jardin_(trillot)',           NULL,   NULL, NULL,         NULL),
    ('jardinots_1',                6759,   0.68, 'CS',         'GV'),
    ('jardinots_2',                6625,   0.63, 'CS',         'GV'),
    ('juillac_1',                 11243,   1.20, 'CS',         'GV'),
    ('juillac_2_CS',               3674,   0.41, 'CS',         'GV'),
    ('juillac_2_M',                4705,   0.54, 'M',          'GV'),
    ('la_glodoune',                3622,   0.43, 'CS',         'PA'),
    ('la_lagune_CS',               2346,   0.07, 'CS',         'FL'),
    ('la_lagune_M',                 767,   0.23, 'M',          'FL'),
    ('les_gressons_3',             1870,   0.07, 'CS',         'FL'),
    ('les_gressons_4',             4253,   0.47, 'CS',         'FL'),
    ('les_gressons_m',             NULL,   NULL, NULL,         NULL),
    ('les_parcs_CS',               1060,   0.11, 'CS',         'FL'),
    ('les_parcs_M',                 761,   0.07, 'M',          'FL'),
    ('lespret',                     520,   NULL, 'CS',         'FL'),
    ('merlots_en_mélange',         NULL,   NULL, NULL,         NULL),
    ('moussas',                   16496,   2.14, 'CS',         'PA'),
    ('parc_de_pelon',              3761,   NULL, 'M',          'FL'),
    ('parcelle_de_la_palu_(hors_aoc)', 554, 0.05, 'HORS AOC', NULL),
    ('petit_batailley_1_CS',       7993,   0.78, 'CS',         'FL'),
    ('petit_batailley_1_M',        6378,   0.61, 'M',          'FL'),
    ('petit_batailley_2',         18030,   1.20, 'CS',         'FL'),
    ('petit_batailley_2_bis',      3415,   0.35, 'CS',         'FL'),
    ('petit_batailley_3',         10258,   1.03, 'CS',         'FL'),
    ('petit_batailley_4',         17585,   0.99, 'CS',         'FL'),
    ('petit_batailley_5',         17453,   1.31, 'CS',         'FL'),
    ('petit_batailley_6',          5037,   0.56, 'CS',         'PA'),
    ('petit_batailley_7',          6536,   0.72, 'M',          'PA'),
    ('petit_batailley_8',          8276,   1.70, 'Plantation', NULL),
    ('petit_faure_1_CS_nord',      2872,   0.33, 'CS',         'GV'),
    ('petit_faure_1_CS_sud',       7001,   0.68, 'CS',         'GV'),
    ('petit_faure_1_M',            5117,   0.57, 'M',          'GV'),
    ('petit_faure_2_CS_nord',      3171,   0.35, 'CS',         'GV'),
    ('petit_faure_2_CS_sud',       7094,   0.77, 'CS',         'GV'),
    ('petit_faure_2_M',            6471,   0.66, 'M',          'GV'),
    ('petit_faure_3',              7434,   0.77, 'CS',         'GV'),
    ('petit_sablonnet',            8932,   2.50, 'CS',         'FL'),
    ('petits_pieds_cs',            NULL,   NULL, NULL,         NULL),
    ('petits_pieds_merlots',       NULL,   NULL, NULL,         NULL),
    ('pibran_1',                   3062,   0.33, 'M',          'PA'),
    ('pibran_2',                   1752,   0.21, 'CS',         'PA'),
    ('piece_de_la_tour',          23250,   1.39, 'CS',         'GV'),
    ('piece_du_chateau',           9988,   1.04, 'CS',         'GV'),
    ('pinada_1',                   2795,   0.29, 'M',          'PA'),
    ('pinada_2',                   NULL,   0.62, 'Terre',      NULL),
    ('pinada_3',                   NULL,   1.60, 'Terre',      NULL),
    ('plantier_blanc_CS',          3253,   0.37, 'CS',         'FL'),
    ('plantier_blanc_PV',           954,   0.10, 'PV',         'PA'),
    ('plantier_comtesse',          2879,   0.31, 'M',          'FL'),
    ('pommiers',                   5029,   0.48, 'CS',         'GV'),
    ('prairie_CS',                 2847,   0.40, 'CS',         'FL'),
    ('prairie_M',                  2960,   0.19, 'M',          'FL'),
    ('roca_1',                     7171,   0.90, 'CS',         'FL'),
    ('roca_2',                     6837,   0.89, 'CS',         'FL'),
    ('roca_3',                     7376,   0.93, 'CS',         'FL'),
    ('roca_4',                     7424,   NULL, 'CS',         'FL'),
    ('rodites_1',                  2977,   0.32, 'CS',         'PA'),
    ('rodites_2_CS',               1315,   0.12, 'CS',         'FL'),
    ('rodites_2_M',                4940,   0.44, 'M',          'FL'),
    ('saint_anne_1',              11588,   1.20, 'CS',         'PA'),
    ('saint_anne_2',               4777,   0.47, 'M',          'PA'),
    ('saint_anne_3',               5814,   0.97, 'CS',         'PA'),
    ('saint_anne_3_plante',        3884,   NULL, NULL,         NULL),
    ('saint_anne_4',               6328,   0.80, 'CS',         'FL'),
    ('saint_anne_5',              10070,   1.31, 'CS',         'FL'),
    ('saint_anne_6',               7262,   1.25, 'CS',         NULL),
    ('sarmentier',                20158,   2.05, 'CS',         'GV'),
    ('saucina',                   11023,   1.12, 'Terre',      NULL),
    ('socs_1_CS',                  4787,   0.54, 'CS',         'GV'),
    ('socs_1_M',                   1628,   0.18, 'M',          'GV'),
    ('socs_2',                     9489,   1.16, 'CS',         'GV'),
    ('socs_3',                     2085,   0.25, 'CS',         'GV'),
    ('terre_noire',                 167,   0.02, 'CS',         'FL'),
    ('tourette_1',                 1195,   0.17, 'CS',         'FL'),
    ('tourette_2',                 3412,   0.48, 'CS',         'FL'),
    ('tourette_3',                 4820,   0.73, 'CS',         'FL'),
    ('tourette_4',                 5801,   0.83, 'CS',         'FL'),
    ('trillot_1',                  5411,   0.61, 'PV',         'FL'),
    ('trillot_2',                  4253,   0.47, 'M',          'FL'),
    ('trillot_2_jeunes_pieds',     1183,   NULL, NULL,         NULL),
    ('trillot_3',                  5856,   0.64, 'CS',         'GV'),
    ('trillot_4',                  2848,   0.28, 'M',          'FL'),
    ('Autre',                      NULL,   NULL, NULL,         NULL)
ON CONFLICT (slug) DO UPDATE SET
    nombre_pieds = EXCLUDED.nombre_pieds,
    surface      = EXCLUDED.surface,
    cepage       = EXCLUDED.cepage,
    gamme        = EXCLUDED.gamme;


-- =============================================================================
-- 5. PERSONNEL (48 ouvriers répartis en 3 équipes)
-- =============================================================================

-- Équipe Carol (14 ouvriers)
INSERT INTO vigne_ouvrier (nom_complet, equipe_id, contrat)
SELECT v.nom, e.id, 'Permanent'
FROM (VALUES
    ('BAQUIERE Yannick'),
    ('CAILLAUD Azeddine'),
    ('CARDENAS Nathalie'),
    ('DUFAURE Carine'),
    ('HOUDART Stéphane'),
    ('KUNTZ Antoine'),
    ('PERLANT Etienne'),
    ('PIERRE Henriette'),
    ('LAHAYE Benoit'),
    ('ESCUDERO Jordan'),
    ('FRANCISCO Aude'),
    ('MONNET Analia'),
    ('BOURGEOIS Jérôme'),
    ('AMESTOY Karine')
) AS v(nom)
JOIN vigne_equipe e ON e.nom = 'Carol'
ON CONFLICT (nom_complet) DO UPDATE SET
    equipe_id = EXCLUDED.equipe_id,
    contrat   = EXCLUDED.contrat;

-- Équipe Thierry (15 ouvriers)
INSERT INTO vigne_ouvrier (nom_complet, equipe_id, contrat)
SELECT v.nom, e.id, 'Permanent'
FROM (VALUES
    ('BAUDOUIN Céline'),
    ('CROMBET Jean-François'),
    ('DENIS Sandrine'),
    ('FAUX Florence'),
    ('FERREIRA David'),
    ('FRAGNAUD Jean-Christophe'),
    ('GONZALES Maeva'),
    ('QUESSADA Carolyn'),
    ('RAMOS Patricia'),
    ('SAMOUDI Rachid'),
    ('ESTRADE SYLVAIN'),
    ('LARROQUE NOA'),
    ('NICOLAS VIALLEY'),
    ('equipe malliage emannuel'),
    ('david')
) AS v(nom)
JOIN vigne_equipe e ON e.nom = 'Thierry'
ON CONFLICT (nom_complet) DO UPDATE SET
    equipe_id = EXCLUDED.equipe_id,
    contrat   = EXCLUDED.contrat;

-- Équipe Michèle (19 ouvriers)
INSERT INTO vigne_ouvrier (nom_complet, equipe_id, contrat)
SELECT v.nom, e.id, 'Permanent'
FROM (VALUES
    ('BARON Jonathan'),
    ('DECOULGENT Stéphanie'),
    ('DESCHAMPS Florence'),
    ('FERNANDEZ Laure'),
    ('FOUGA Karine'),
    ('HUYOT Wilfried'),
    ('LOCATELLI Margaux'),
    ('PATARIN Hugo'),
    ('PORTE Baptiste'),
    ('ROBERT Perrine'),
    ('THOMAS Damien'),
    ('BABIN Christopher'),
    ('DUCOURNEAU Corinne'),
    ('RAMOS Christophe'),
    ('VERGONJEANNE Marc'),
    ('DELETREZ Clémence'),
    ('ROUSSEAU Alicia'),
    ('LE LOUET Nathalie'),
    ('LACOSTE Coralie')
) AS v(nom)
JOIN vigne_equipe e ON e.nom = 'Michèle'
ON CONFLICT (nom_complet) DO UPDATE SET
    equipe_id = EXCLUDED.equipe_id,
    contrat   = EXCLUDED.contrat;


-- =============================================================================
-- 4b. GÉOMÉTRIES (optionnel – coordonnées Lambert 93 / EPSG:2154)
-- Upsert : ajoute la géométrie si absente, remplace si déjà présente.
-- Vous pouvez sauter cette section si la carte n'est pas utilisée.
-- Précision : 1 décimale (≈ 10 cm), suffisant pour la viticulture.
-- =============================================================================

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grande_lande_1', '{"type":"MultiPolygon","coordinates":[[[[404518.1,6461192.2],[404520.4,6461194.7],[404524.9,6461199.6],[404531.7,6461206.7],[404536.4,6461211.4],[404539.3,6461214.3],[404544.2,6461219.0],[404550.2,6461224.9],[404556.0,6461230.5],[404563.0,6461237.3],[404568.7,6461243.1],[404569.7,6461244.2],[404573.6,6461248.4],[404577.5,6461252.9],[404580.2,6461256.2],[404583.6,6461260.5],[404586.0,6461263.8],[404588.2,6461267.1],[404591.1,6461271.7],[404596.5,6461281.0],[404613.0,6461271.8],[404609.2,6461265.1],[404605.2,6461258.2],[404599.8,6461250.4],[404593.7,6461242.2],[404586.1,6461233.8],[404579.2,6461226.3],[404570.0,6461217.2],[404560.8,6461208.1],[404549.6,6461197.2],[404536.0,6461182.8],[404518.1,6461192.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('pinada_1', '{"type":"MultiPolygon","coordinates":[[[[404667.4,6459645.8],[404578.0,6459651.4],[404587.2,6459661.6],[404594.4,6459668.6],[404600.9,6459673.0],[404621.7,6459683.8],[404634.2,6459683.0],[404634.3,6459684.2],[404653.5,6459682.8],[404653.6,6459684.0],[404672.8,6459682.8],[404673.0,6459683.9],[404691.9,6459682.7],[404692.0,6459683.8],[404697.1,6459683.2],[404691.4,6459646.4],[404667.9,6459647.9],[404667.4,6459645.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('socs_1_M', '{"type":"MultiPolygon","coordinates":[[[[406084.0,6459984.6],[406161.8,6459989.4],[406191.5,6459991.1],[406192.4,6459984.5],[406130.2,6459969.0],[406101.1,6459961.5],[406089.0,6459960.8],[406084.0,6459984.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grand_sablonnet_2_sud', '{"type":"MultiPolygon","coordinates":[[[[406100.1,6460243.6],[406149.2,6460247.6],[406167.2,6460249.1],[406169.5,6460249.3],[406169.7,6460245.6],[406171.9,6460201.9],[406161.1,6460201.0],[406100.9,6460196.0],[406100.1,6460243.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_faure_2_CS_sud', '{"type":"MultiPolygon","coordinates":[[[[405870.3,6460178.6],[405811.0,6460191.9],[405827.3,6460311.1],[405856.1,6460305.0],[405891.1,6460296.6],[405870.3,6460178.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grand_sablonnet_2_collection', '{"type":"MultiPolygon","coordinates":[[[[406169.5,6460249.3],[406167.2,6460249.1],[406149.2,6460247.6],[406100.1,6460243.6],[406097.1,6460445.3],[406159.0,6460449.9],[406164.8,6460343.2],[406169.5,6460249.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grand_sablonnet_1', '{"type":"MultiPolygon","coordinates":[[[[406014.5,6460191.0],[406011.5,6460193.0],[406029.6,6460300.7],[406031.8,6460316.1],[406034.2,6460342.4],[406034.6,6460347.3],[406033.1,6460439.3],[406034.2,6460441.3],[406091.3,6460445.7],[406095.3,6460195.6],[406088.8,6460195.1],[406060.6,6460193.0],[406014.5,6460191.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grand_faure_1', '{"type":"MultiPolygon","coordinates":[[[[405936.9,6460168.0],[405919.9,6460170.9],[405874.8,6460178.7],[405889.8,6460262.6],[405902.6,6460334.7],[405914.2,6460398.6],[405916.2,6460415.9],[405917.3,6460438.1],[405918.2,6460450.0],[405919.0,6460451.8],[405974.1,6460446.0],[405969.6,6460410.1],[405954.1,6460296.5],[405937.8,6460175.2],[405936.9,6460168.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('socs_3', '{"type":"MultiPolygon","coordinates":[[[[406124.6,6459800.6],[406121.6,6459814.0],[406117.0,6459835.0],[406118.2,6459837.9],[406157.7,6459847.1],[406179.2,6459851.9],[406191.5,6459854.6],[406209.0,6459858.4],[406209.3,6459854.7],[406206.2,6459848.9],[406184.7,6459827.7],[406139.4,6459803.8],[406124.6,6459800.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_sablonnet', '{"type":"MultiPolygon","coordinates":[[[[406275.9,6460343.0],[406170.0,6460346.3],[406164.7,6460452.5],[406230.4,6460450.2],[406275.9,6460343.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('prairie_M', '{"type":"MultiPolygon","coordinates":[[[[406254.0,6460136.9],[406208.5,6460131.4],[406218.5,6460168.3],[406223.0,6460184.3],[406225.6,6460196.1],[406227.8,6460202.0],[406267.6,6460206.8],[406268.8,6460203.8],[406269.1,6460200.1],[406254.0,6460136.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('canterane', '{"type":"MultiPolygon","coordinates":[[[[406033.9,6460447.5],[406033.5,6460474.7],[406033.1,6460517.4],[406032.7,6460546.7],[406123.7,6460541.5],[406130.8,6460539.9],[406136.6,6460537.7],[406154.9,6460530.9],[406217.1,6460506.8],[406222.0,6460500.5],[406223.0,6460497.6],[406224.2,6460492.4],[406227.5,6460462.6],[406227.5,6460455.6],[406225.9,6460455.7],[406186.3,6460456.7],[406164.7,6460457.2],[406156.2,6460456.5],[406104.5,6460451.8],[406033.9,6460447.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('juillac_2_CS', '{"type":"MultiPolygon","coordinates":[[[[405694.7,6459685.4],[405765.4,6459702.7],[405814.0,6459714.5],[405815.3,6459706.5],[405816.8,6459699.5],[405740.4,6459655.4],[405704.5,6459635.1],[405694.7,6459685.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('garennes_2', '{"type":"MultiPolygon","coordinates":[[[[406033.4,6459774.1],[405947.5,6459752.8],[405931.8,6459802.7],[405989.2,6459815.3],[405990.0,6459812.0],[405994.9,6459813.1],[405994.2,6459816.4],[406020.7,6459822.3],[406033.4,6459774.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('chataigniers', '{"type":"MultiPolygon","coordinates":[[[[405924.3,6460107.4],[405901.7,6460110.6],[405863.6,6460116.0],[405873.7,6460171.9],[405918.0,6460164.8],[405931.6,6460162.6],[405934.3,6460160.9],[405930.6,6460139.5],[405924.3,6460107.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('parcelle_de_la_palu_(hors_aoc)', '{"type":"MultiPolygon","coordinates":[[[[406234.2,6460062.2],[406235.0,6460059.5],[406235.0,6460056.7],[406233.3,6460049.1],[406190.5,6460046.5],[406189.3,6460047.3],[406188.2,6460051.1],[406188.2,6460056.0],[406188.3,6460056.9],[406189.5,6460059.9],[406234.2,6460062.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('bois_de_la_tour', '{"type":"MultiPolygon","coordinates":[[[[405676.8,6459916.2],[405671.6,6459925.2],[405650.0,6459963.3],[405619.0,6460021.6],[405616.9,6460027.0],[405627.2,6460026.7],[405651.2,6460024.9],[405679.6,6460024.8],[405706.6,6460024.1],[405708.7,6460020.1],[405693.1,6459927.8],[405691.8,6459920.1],[405676.8,6459916.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_faure_2_M', '{"type":"MultiPolygon","coordinates":[[[[405838.8,6460404.3],[405844.1,6460403.8],[405869.2,6460401.8],[405893.2,6460400.6],[405909.7,6460398.7],[405899.0,6460338.8],[405898.0,6460333.4],[405892.0,6460300.1],[405882.0,6460301.3],[405826.8,6460314.8],[405832.0,6460354.4],[405834.1,6460369.5],[405838.8,6460404.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_faure_1_M', '{"type":"MultiPolygon","coordinates":[[[[405762.4,6460410.3],[405786.6,6460408.9],[405810.5,6460406.5],[405834.8,6460404.6],[405828.2,6460353.8],[405825.8,6460336.5],[405821.9,6460314.6],[405799.3,6460316.8],[405768.8,6460320.5],[405769.4,6460327.0],[405769.0,6460334.9],[405765.5,6460365.7],[405763.4,6460387.7],[405762.4,6460410.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_8', '{"type":"MultiPolygon","coordinates":[[[[404083.5,6459973.9],[404130.5,6460018.6],[404266.5,6460027.4],[404268.2,6460026.9],[404281.7,6460039.9],[404283.9,6460037.9],[404286.0,6460039.9],[404287.4,6460039.6],[404305.2,6460015.5],[404194.4,6459909.6],[404166.9,6459926.2],[404083.5,6459973.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('tourette_4', '{"type":"MultiPolygon","coordinates":[[[[402928.3,6459164.1],[402929.3,6459163.0],[402931.1,6459164.8],[403031.8,6459130.2],[403032.5,6459127.9],[403025.2,6459122.1],[402953.2,6459077.6],[402948.0,6459077.1],[402853.3,6459104.2],[402871.4,6459118.8],[402872.7,6459118.2],[402928.3,6459164.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('prairie_CS', '{"type":"MultiPolygon","coordinates":[[[[406190.5,6460063.0],[406208.5,6460131.4],[406254.0,6460136.9],[406237.3,6460067.5],[406235.1,6460065.3],[406224.8,6460064.4],[406224.8,6460065.5],[406205.1,6460063.4],[406205.1,6460064.5],[406190.5,6460063.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('chais_1', '{"type":"MultiPolygon","coordinates":[[[[406022.6,6459938.7],[406085.0,6459953.4],[406098.5,6459894.5],[406097.7,6459892.9],[406096.9,6459891.9],[406074.7,6459886.8],[406050.7,6459881.3],[406030.6,6459876.8],[406024.8,6459875.5],[406020.9,6459892.9],[406017.8,6459906.7],[406018.6,6459906.9],[406014.2,6459927.0],[406017.9,6459927.9],[406016.6,6459934.4],[406022.6,6459938.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('chene_vert_2', '{"type":"MultiPolygon","coordinates":[[[[405580.9,6459786.9],[405577.8,6459794.5],[405552.9,6459855.0],[405553.7,6459857.9],[405556.3,6459859.9],[405579.5,6459868.0],[405591.7,6459870.8],[405688.0,6459890.2],[405690.3,6459884.9],[405693.4,6459842.3],[405695.5,6459819.5],[405696.0,6459813.5],[405694.8,6459811.2],[405672.1,6459806.0],[405622.3,6459795.4],[405580.9,6459786.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('socs_1_CS', '{"type":"MultiPolygon","coordinates":[[[[406073.9,6460033.3],[406091.9,6460034.4],[406147.7,6460037.8],[406184.8,6460039.9],[406191.5,6459991.1],[406084.0,6459984.6],[406073.9,6460033.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('font_de_jeanne_M', '{"type":"MultiPolygon","coordinates":[[[[405808.0,6460512.1],[405808.0,6460510.9],[405808.3,6460457.1],[405759.4,6460456.3],[405756.0,6460506.1],[405791.7,6460521.3],[405799.1,6460523.6],[405907.9,6460525.9],[405808.0,6460512.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('plantier_blanc_CS', '{"type":"MultiPolygon","coordinates":[[[[405666.6,6460030.2],[405678.5,6460114.9],[405723.7,6460108.8],[405710.3,6460029.2],[405666.6,6460030.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('font_de_jeanne_PV', '{"type":"MultiPolygon","coordinates":[[[[405808.0,6460512.1],[405907.9,6460525.9],[405863.9,6460540.3],[406018.6,6460552.0],[406027.1,6460547.2],[406027.5,6460523.6],[405952.5,6460507.4],[405808.0,6460512.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_1_M', '{"type":"MultiPolygon","coordinates":[[[[404173.1,6459741.1],[404172.0,6459743.4],[404282.8,6459849.1],[404286.1,6459847.5],[404304.8,6459812.0],[404207.6,6459719.5],[404174.7,6459738.4],[404173.1,6459741.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('plantier_blanc_PV', '{"type":"MultiPolygon","coordinates":[[[[405678.5,6460114.9],[405666.6,6460030.2],[405656.0,6460030.6],[405658.4,6460051.6],[405667.4,6460116.3],[405678.5,6460114.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('saint_anne_1', '{"type":"MultiPolygon","coordinates":[[[[402970.1,6459535.5],[403008.4,6459604.2],[403010.2,6459606.4],[403012.7,6459607.0],[403125.4,6459597.0],[403127.5,6459594.8],[403037.1,6459466.9],[402970.1,6459535.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('socs_2', '{"type":"MultiPolygon","coordinates":[[[[406115.1,6459842.7],[406111.7,6459858.2],[406097.6,6459922.6],[406090.7,6459954.7],[406164.1,6459971.8],[406166.8,6459972.4],[406192.9,6459978.4],[406193.5,6459977.3],[406197.8,6459946.4],[406207.7,6459873.1],[406207.7,6459870.0],[406207.0,6459865.8],[406204.4,6459863.3],[406163.1,6459853.6],[406115.1,6459842.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('saint_anne_3', '{"type":"MultiPolygon","coordinates":[[[[403015.5,6459423.9],[403068.7,6459499.2],[403148.8,6459417.9],[403084.8,6459352.5],[403015.5,6459423.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('forts_de_latour_CS_nord', '{"type":"MultiPolygon","coordinates":[[[[405727.3,6460347.7],[405760.3,6460332.8],[405760.9,6460324.6],[405748.7,6460254.1],[405742.0,6460216.4],[405697.1,6460221.2],[405674.6,6460223.7],[405666.5,6460227.2],[405678.8,6460268.4],[405717.9,6460333.1],[405727.3,6460347.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_faure_1_CS_sud', '{"type":"MultiPolygon","coordinates":[[[[405768.8,6460320.5],[405799.3,6460316.8],[405821.9,6460314.6],[405821.6,6460312.4],[405827.3,6460311.1],[405811.0,6460191.9],[405806.4,6460192.9],[405806.1,6460192.0],[405747.7,6460205.6],[405747.6,6460207.5],[405757.5,6460259.8],[405768.8,6460320.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('forts_de_latour_M', '{"type":"MultiPolygon","coordinates":[[[[405661.0,6460187.9],[405687.8,6460184.9],[405731.8,6460179.9],[405735.7,6460179.5],[405724.6,6460115.0],[405668.2,6460121.3],[405651.7,6460123.1],[405659.7,6460178.7],[405661.0,6460187.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_faure_3', '{"type":"MultiPolygon","coordinates":[[[[405863.6,6460116.0],[405759.6,6460131.8],[405737.8,6460135.2],[405733.9,6460141.1],[405745.1,6460198.3],[405750.5,6460200.4],[405771.2,6460195.5],[405862.5,6460173.7],[405873.7,6460171.9],[405863.6,6460116.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('saint_anne_2', '{"type":"MultiPolygon","coordinates":[[[[403052.2,6459320.2],[402990.1,6459375.3],[402984.8,6459381.0],[403015.5,6459423.9],[403084.8,6459352.5],[403052.2,6459320.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gravette', '{"type":"MultiPolygon","coordinates":[[[[405594.9,6459729.5],[405592.5,6459730.9],[405583.0,6459780.2],[405584.6,6459782.1],[405667.6,6459800.3],[405715.8,6459810.7],[405782.8,6459825.9],[405785.1,6459825.1],[405786.3,6459823.9],[405788.4,6459823.6],[405789.7,6459822.0],[405791.4,6459817.9],[405801.4,6459777.5],[405799.1,6459773.7],[405774.8,6459768.4],[405627.7,6459736.7],[405594.9,6459729.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('trillot_4', '{"type":"MultiPolygon","coordinates":[[[[405598.8,6460310.3],[405619.4,6460298.5],[405639.0,6460287.0],[405672.4,6460267.8],[405663.4,6460248.5],[405628.2,6460251.5],[405611.7,6460254.2],[405610.2,6460254.4],[405586.8,6460258.2],[405596.1,6460266.8],[405598.2,6460271.1],[405598.4,6460308.9],[405598.8,6460310.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('trillot_1', '{"type":"MultiPolygon","coordinates":[[[[405709.0,6460484.9],[405714.8,6460489.1],[405720.1,6460492.4],[405747.3,6460508.1],[405749.9,6460505.9],[405759.3,6460370.7],[405669.9,6460451.4],[405685.3,6460465.7],[405693.0,6460472.3],[405700.0,6460478.1],[405709.0,6460484.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('chais_2', '{"type":"MultiPolygon","coordinates":[[[[406025.9,6459822.9],[406024.7,6459823.2],[406015.9,6459864.6],[406016.6,6459865.8],[406026.4,6459868.0],[406025.8,6459870.5],[406100.2,6459887.3],[406107.8,6459851.9],[406110.0,6459841.4],[406098.0,6459838.6],[406025.9,6459822.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('pommiers', '{"type":"MultiPolygon","coordinates":[[[[406040.0,6460039.7],[406041.5,6460054.2],[406047.5,6460094.9],[406049.4,6460095.0],[406070.1,6460088.4],[406089.0,6460082.8],[406117.6,6460075.3],[406171.2,6460064.2],[406172.3,6460060.8],[406181.4,6460061.7],[406184.9,6460055.6],[406183.2,6460047.2],[406092.6,6460042.1],[406040.0,6460039.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_faure_1_CS_nord', '{"type":"MultiPolygon","coordinates":[[[[405762.4,6460410.3],[405761.5,6460434.7],[405761.3,6460447.8],[405762.8,6460449.7],[405809.0,6460450.6],[405834.2,6460450.8],[405841.0,6460450.2],[405834.8,6460404.6],[405810.5,6460406.5],[405786.6,6460408.9],[405762.4,6460410.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('tourette_1', '{"type":"MultiPolygon","coordinates":[[[[403079.2,6459313.2],[403173.5,6459404.6],[403185.4,6459399.2],[403188.3,6459397.3],[403177.4,6459386.8],[403176.2,6459387.6],[403154.6,6459366.4],[403153.5,6459366.9],[403128.2,6459342.8],[403127.0,6459343.4],[403103.0,6459319.8],[403101.9,6459320.7],[403089.3,6459308.8],[403079.2,6459313.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('tourette_2', '{"type":"MultiPolygon","coordinates":[[[[402999.9,6459230.0],[403073.6,6459306.3],[403124.8,6459288.8],[403123.1,6459286.8],[403034.0,6459211.1],[402999.9,6459230.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('saint_anne_4', '{"type":"MultiPolygon","coordinates":[[[[403072.0,6459504.5],[403106.2,6459552.7],[403110.7,6459557.1],[403200.5,6459464.5],[403156.0,6459419.5],[403072.0,6459504.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('juillac_1', '{"type":"MultiPolygon","coordinates":[[[[405770.6,6459707.9],[405607.9,6459667.6],[405604.5,6459668.2],[405599.5,6459694.0],[405593.7,6459723.6],[405597.2,6459726.0],[405623.8,6459731.6],[405779.5,6459765.5],[405797.7,6459769.5],[405803.9,6459767.7],[405813.8,6459729.1],[405815.4,6459722.0],[405812.2,6459718.1],[405770.6,6459707.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_faure_2_CS_nord', '{"type":"MultiPolygon","coordinates":[[[[405838.8,6460404.3],[405840.9,6460422.2],[405843.8,6460443.3],[405845.4,6460449.1],[405846.7,6460451.0],[405888.9,6460451.5],[405907.7,6460451.7],[405913.1,6460451.3],[405913.3,6460448.4],[405911.7,6460416.3],[405909.7,6460398.7],[405893.2,6460400.6],[405869.2,6460401.8],[405844.1,6460403.8],[405838.8,6460404.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('tourette_3', '{"type":"MultiPolygon","coordinates":[[[[402935.6,6459170.4],[402969.3,6459198.3],[402967.9,6459199.2],[402988.8,6459215.8],[402991.0,6459213.8],[402993.0,6459215.8],[403027.2,6459196.2],[403032.1,6459193.9],[403035.4,6459193.1],[403102.2,6459181.2],[403041.8,6459132.3],[402936.7,6459168.6],[402935.6,6459170.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('saint_anne_5', '{"type":"MultiPolygon","coordinates":[[[[403113.1,6459562.4],[403132.8,6459590.1],[403138.1,6459595.2],[403311.8,6459578.7],[403204.2,6459468.8],[403113.1,6459562.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('les_gressons_3', '{"type":"MultiPolygon","coordinates":[[[[405045.2,6460651.4],[405075.4,6460654.2],[405072.8,6460592.0],[405042.2,6460596.6],[405045.2,6460651.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('juillac_2_M', '{"type":"MultiPolygon","coordinates":[[[[405694.7,6459685.4],[405704.5,6459634.9],[405686.6,6459624.9],[405659.3,6459609.5],[405635.6,6459599.1],[405633.2,6459599.0],[405631.2,6459599.6],[405614.1,6459635.1],[405610.2,6459644.6],[405607.7,6459652.9],[405606.1,6459661.4],[405608.6,6459663.8],[405623.1,6459667.7],[405694.7,6459685.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('jardinots_1', '{"type":"MultiPolygon","coordinates":[[[[405807.3,6459775.4],[405794.0,6459827.4],[405802.1,6459829.2],[405822.8,6459833.8],[405836.2,6459836.8],[405875.6,6459845.7],[405912.6,6459854.8],[405915.8,6459854.3],[405918.9,6459841.7],[405924.5,6459818.1],[405929.0,6459802.2],[405807.3,6459775.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grand_faure_2', '{"type":"MultiPolygon","coordinates":[[[[405980.6,6460444.7],[406024.1,6460439.9],[406024.0,6460438.9],[406026.9,6460438.6],[406027.9,6460435.0],[406028.5,6460339.2],[406027.1,6460323.2],[405996.1,6460133.6],[405994.1,6460128.0],[405990.8,6460123.7],[405963.7,6460110.9],[405956.6,6460106.3],[405951.4,6460102.8],[405932.2,6460108.0],[405943.6,6460168.8],[405980.6,6460444.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('sarmentier', '{"type":"MultiPolygon","coordinates":[[[[405776.0,6460020.3],[405759.9,6460021.2],[405738.0,6460024.5],[405726.3,6460026.3],[405717.9,6460028.1],[405714.5,6460031.5],[405731.5,6460128.6],[405736.9,6460131.0],[405738.8,6460131.1],[405810.6,6460120.3],[405851.3,6460114.2],[405937.9,6460100.7],[405947.8,6460098.8],[405950.6,6460097.4],[405939.9,6460032.8],[405937.4,6460030.9],[405904.6,6460025.9],[405894.5,6460024.7],[405871.8,6460022.7],[405834.0,6460021.2],[405798.6,6460020.3],[405776.0,6460020.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('trillot_2', '{"type":"MultiPolygon","coordinates":[[[[405760.6,6460346.4],[405731.4,6460359.8],[405704.7,6460379.0],[405682.2,6460395.4],[405646.7,6460422.6],[405670.8,6460446.6],[405667.8,6460449.4],[405669.2,6460450.7],[405696.2,6460426.4],[405759.4,6460369.5],[405761.9,6460346.6],[405760.6,6460346.4]]],[[[405652.6,6460378.7],[405654.5,6460382.2],[405627.6,6460401.2],[405627.3,6460402.7],[405628.9,6460405.3],[405643.8,6460420.0],[405645.7,6460418.3],[405646.4,6460419.1],[405670.4,6460400.8],[405700.1,6460378.9],[405727.7,6460358.5],[405713.6,6460336.5],[405652.6,6460378.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('font_de_jeanne_CS', '{"type":"MultiPolygon","coordinates":[[[[406027.5,6460522.4],[406028.8,6460452.8],[406020.5,6460447.7],[405920.5,6460458.3],[405918.5,6460458.4],[405914.5,6460458.5],[405808.3,6460457.1],[405808.0,6460510.9],[405952.4,6460506.8],[406027.5,6460522.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('chene_vert_1', '{"type":"MultiPolygon","coordinates":[[[[405571.1,6459871.3],[405567.8,6459883.2],[405569.1,6459885.3],[405571.1,6459886.6],[405585.8,6459890.3],[405690.0,6459914.1],[405690.6,6459911.8],[405688.5,6459898.7],[405686.7,6459897.4],[405687.2,6459895.5],[405683.2,6459893.9],[405571.1,6459871.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('trillot_3', '{"type":"MultiPolygon","coordinates":[[[[405599.5,6460310.6],[405606.5,6460322.6],[405621.8,6460338.9],[405635.9,6460353.8],[405643.9,6460365.3],[405689.4,6460340.6],[405709.7,6460329.5],[405685.5,6460290.1],[405672.4,6460267.8],[405639.0,6460287.0],[405619.4,6460298.5],[405599.5,6460310.6]]],[[[405652.6,6460378.7],[405713.6,6460336.5],[405711.0,6460332.1],[405688.7,6460343.9],[405670.5,6460354.1],[405645.4,6460367.4],[405649.3,6460373.5],[405652.6,6460378.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('canaires', '{"type":"MultiPolygon","coordinates":[[[[406068.1,6459782.7],[406037.5,6459775.1],[406030.3,6459799.8],[406026.0,6459818.5],[406042.2,6459822.3],[406110.9,6459837.2],[406120.2,6459800.4],[406117.0,6459797.3],[406101.7,6459792.0],[406068.1,6459782.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('piece_du_chateau', '{"type":"MultiPolygon","coordinates":[[[[406008.7,6459948.1],[405978.6,6459942.1],[405958.3,6459937.9],[405956.4,6459938.3],[405954.5,6459958.3],[405951.4,6459996.8],[405950.3,6460008.5],[405948.8,6460025.2],[405950.7,6460025.5],[406067.2,6460033.5],[406068.3,6460032.6],[406083.9,6459959.0],[406081.2,6459957.0],[406056.1,6459951.1],[406029.1,6459944.8],[406020.5,6459943.0],[406014.5,6459943.2],[406013.1,6459945.2],[406010.8,6459947.4],[406008.7,6459948.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('chene_vert_3', '{"type":"MultiPolygon","coordinates":[[[[405907.6,6459934.5],[405910.5,6459934.3],[405914.6,6459915.5],[405918.4,6459899.4],[405921.9,6459884.5],[405925.3,6459869.5],[405926.3,6459861.7],[405915.4,6459859.4],[405892.1,6459853.3],[405867.6,6459848.0],[405842.5,6459842.5],[405818.8,6459837.4],[405789.6,6459831.1],[405764.1,6459825.8],[405739.6,6459820.6],[405714.5,6459815.4],[405702.6,6459812.9],[405700.3,6459814.2],[405696.8,6459849.6],[405694.8,6459874.9],[405694.2,6459890.3],[405695.9,6459891.7],[405731.8,6459898.8],[405756.3,6459903.7],[405780.9,6459908.6],[405811.3,6459914.7],[405835.1,6459919.5],[405860.0,6459924.3],[405884.4,6459929.0],[405907.6,6459934.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('jardinots_2', '{"type":"MultiPolygon","coordinates":[[[[405942.6,6459750.8],[405820.8,6459721.2],[405807.3,6459775.4],[405929.0,6459802.2],[405942.6,6459750.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('garennes_1', '{"type":"MultiPolygon","coordinates":[[[[405931.8,6459802.7],[405931.6,6459803.5],[405931.1,6459805.5],[405922.1,6459842.6],[405924.7,6459844.9],[405983.2,6459858.5],[406006.2,6459863.7],[406010.2,6459863.6],[406011.4,6459863.0],[406020.7,6459822.3],[405994.2,6459816.4],[405992.8,6459822.2],[405991.6,6459821.9],[405987.8,6459821.5],[405989.2,6459815.3],[405970.6,6459811.0],[405931.8,6459802.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grand_enclos', '{"type":"MultiPolygon","coordinates":[[[[406114.6,6460190.7],[406174.6,6460196.4],[406223.3,6460200.8],[406185.4,6460067.0],[406126.4,6460080.5],[406106.3,6460085.3],[406089.9,6460089.9],[406071.9,6460095.0],[406048.0,6460102.9],[406002.5,6460120.1],[406008.3,6460164.1],[406010.8,6460184.2],[406011.9,6460185.6],[406089.0,6460189.4],[406114.6,6460190.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('plantier_comtesse', '{"type":"MultiPolygon","coordinates":[[[[405614.5,6460108.0],[405640.5,6460104.5],[405643.4,6460108.4],[405642.9,6460111.6],[405643.9,6460112.3],[405643.5,6460116.6],[405645.5,6460118.0],[405660.5,6460116.6],[405662.7,6460116.0],[405663.1,6460111.9],[405652.0,6460031.4],[405623.7,6460033.3],[405620.4,6460035.7],[405614.5,6460108.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('la_glodoune', '{"type":"MultiPolygon","coordinates":[[[[404324.9,6460997.2],[404370.8,6460996.5],[404373.3,6460905.1],[404327.4,6460899.7],[404324.9,6460997.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('les_gressons_4', '{"type":"MultiPolygon","coordinates":[[[[405033.6,6460654.2],[405035.5,6460722.5],[405042.2,6460722.6],[405042.3,6460724.8],[405065.1,6460727.5],[405101.0,6460730.1],[405100.9,6460724.7],[405103.8,6460724.6],[405101.7,6460661.9],[405081.6,6460659.7],[405055.4,6460657.6],[405046.2,6460656.6],[405033.6,6460654.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('la_lagune_CS', '{"type":"MultiPolygon","coordinates":[[[[404808.4,6460681.3],[404808.4,6460661.2],[404800.4,6460660.0],[404800.4,6460668.6],[404800.4,6460676.2],[404800.3,6460691.0],[404800.5,6460720.3],[404800.7,6460731.2],[404801.2,6460750.4],[404809.8,6460749.9],[404809.4,6460735.1],[404808.9,6460718.7],[404808.6,6460700.1],[404808.4,6460681.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('les_parcs_M', '{"type":"MultiPolygon","coordinates":[[[[404691.7,6460660.5],[404690.7,6460645.6],[404690.1,6460625.9],[404689.9,6460610.7],[404690.1,6460600.9],[404690.7,6460588.9],[404681.1,6460582.5],[404680.8,6460596.1],[404680.6,6460607.5],[404680.7,6460615.8],[404680.8,6460622.8],[404681.0,6460632.1],[404681.4,6460641.6],[404681.8,6460649.4],[404682.6,6460661.2],[404691.7,6460660.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('fiouzaille', '{"type":"MultiPolygon","coordinates":[[[[404197.5,6460208.9],[404206.7,6460212.5],[404215.8,6460179.8],[404214.9,6460179.5],[404215.8,6460176.0],[404217.8,6460175.7],[404228.2,6460138.3],[404227.4,6460138.0],[404228.5,6460134.3],[404229.5,6460134.5],[404229.8,6460133.2],[404231.0,6460133.1],[404233.3,6460124.3],[404220.4,6460124.3],[404197.5,6460208.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('roca_1', '{"type":"MultiPolygon","coordinates":[[[[403371.6,6460103.1],[403336.5,6460034.8],[403310.9,6460049.6],[403308.3,6460051.1],[403236.4,6460092.2],[403271.2,6460160.5],[403371.6,6460103.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('saint_anne_6', '{"type":"MultiPolygon","coordinates":[[[[402970.1,6459535.5],[403037.1,6459466.9],[402966.7,6459367.9],[402963.7,6459367.6],[402890.4,6459385.2],[402888.1,6459387.8],[402970.1,6459535.5]]],[[[402886.4,6459384.4],[402937.6,6459477.4],[402999.1,6459410.5],[402967.8,6459365.8],[402886.4,6459384.4]]],[[[402937.6,6459477.4],[402970.7,6459535.3],[403037.1,6459466.9],[402999.1,6459410.5],[402937.6,6459477.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('roca_2', '{"type":"MultiPolygon","coordinates":[[[[403371.6,6460103.1],[403389.0,6460091.8],[403419.7,6460070.5],[403429.6,6460063.9],[403434.9,6460060.9],[403462.9,6460045.4],[403469.9,6460041.7],[403474.9,6460039.3],[403441.2,6459973.7],[403336.5,6460034.8],[403371.6,6460103.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('moussas', '{"type":"MultiPolygon","coordinates":[[[[404093.4,6460617.8],[404204.0,6460635.6],[404212.1,6460574.4],[404228.7,6460469.9],[404091.0,6460444.0],[404090.6,6460445.2],[404088.9,6460445.0],[404093.4,6460617.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_7', '{"type":"MultiPolygon","coordinates":[[[[404162.1,6460040.6],[404160.0,6460054.9],[404160.8,6460054.9],[404159.7,6460062.8],[404160.7,6460062.9],[404155.9,6460095.4],[404277.1,6460107.9],[404286.1,6460046.4],[404162.1,6460040.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_6', '{"type":"MultiPolygon","coordinates":[[[[404048.7,6460051.5],[404061.5,6460059.2],[404070.8,6460063.9],[404100.4,6460077.1],[404108.1,6460079.7],[404107.9,6460080.6],[404151.0,6460094.6],[404156.0,6460040.6],[404127.1,6460031.2],[404127.3,6460029.7],[404094.5,6460004.0],[404082.7,6460000.0],[404048.7,6460051.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_3', '{"type":"MultiPolygon","coordinates":[[[[404014.2,6459923.1],[404009.8,6459923.9],[404007.9,6459924.7],[403945.4,6459960.6],[403943.5,6459962.2],[403942.1,6459964.5],[403941.3,6459969.2],[403941.4,6459976.3],[403942.2,6459986.7],[403943.5,6459998.0],[403949.3,6460003.7],[404036.0,6460044.8],[404040.5,6460044.3],[404043.8,6460043.1],[404078.4,6459991.8],[404079.1,6459989.7],[404078.7,6459984.7],[404066.3,6459972.2],[404049.1,6459955.8],[404014.2,6459923.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_5', '{"type":"MultiPolygon","coordinates":[[[[404305.2,6460015.5],[404323.9,6459989.6],[404329.1,6459982.2],[404338.0,6459968.6],[404343.2,6459959.8],[404358.1,6459932.4],[404358.4,6459931.3],[404358.3,6459926.6],[404287.8,6459859.2],[404283.0,6459859.3],[404280.8,6459860.1],[404194.1,6459909.4],[404194.4,6459909.6],[404305.2,6460015.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('pinada_2', '{"type":"MultiPolygon","coordinates":[[[[404677.7,6459557.7],[404556.3,6459629.7],[404551.7,6459632.8],[404564.8,6459644.2],[404686.5,6459636.0],[404687.5,6459635.5],[404689.1,6459634.3],[404689.7,6459633.5],[404689.9,6459632.1],[404679.0,6459558.8],[404677.7,6459557.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_1_CS', '{"type":"MultiPolygon","coordinates":[[[[404207.6,6459719.5],[404304.8,6459812.0],[404334.9,6459754.1],[404335.0,6459748.2],[404279.4,6459691.0],[404272.6,6459684.8],[404268.3,6459685.3],[404207.6,6459719.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_2_bis', '{"type":"MultiPolygon","coordinates":[[[[403941.3,6459953.1],[403943.1,6459955.0],[403945.3,6459954.4],[403946.9,6459953.8],[404005.0,6459920.7],[404006.0,6459919.6],[404007.3,6459916.7],[403955.8,6459867.5],[403951.9,6459867.2],[403948.1,6459867.7],[403942.4,6459870.7],[403939.7,6459872.4],[403938.6,6459874.2],[403937.8,6459876.9],[403937.7,6459878.9],[403941.1,6459914.5],[403941.4,6459918.2],[403941.3,6459953.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('pinada_3', '{"type":"MultiPolygon","coordinates":[[[[404548.3,6459629.9],[404677.6,6459553.1],[404677.9,6459550.5],[404665.3,6459466.5],[404663.5,6459465.0],[404480.5,6459563.4],[404479.8,6459564.2],[404479.3,6459565.0],[404478.8,6459567.8],[404548.3,6459629.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_centre_4', '{"type":"MultiPolygon","coordinates":[[[[403475.6,6461892.4],[403434.7,6461885.6],[403433.0,6461897.7],[403433.0,6461917.4],[403436.2,6462000.8],[403438.2,6462017.2],[403453.1,6462016.4],[403487.7,6462010.5],[403475.6,6461892.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_les_chevres_3', '{"type":"MultiPolygon","coordinates":[[[[402917.5,6461776.5],[402913.6,6461720.8],[402839.2,6461720.3],[402873.1,6461779.5],[402877.6,6461779.7],[402917.5,6461776.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_les_chevres_1', '{"type":"MultiPolygon","coordinates":[[[[402932.4,6461805.3],[402933.5,6461805.2],[402934.2,6461811.8],[403017.9,6461801.2],[403010.9,6461770.0],[402930.2,6461780.6],[402932.4,6461805.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('pibran_1', '{"type":"MultiPolygon","coordinates":[[[[403908.0,6462289.4],[403830.8,6462292.9],[403820.0,6462294.2],[403822.3,6462334.4],[403908.4,6462324.1],[403909.3,6462312.4],[403908.8,6462309.3],[403909.1,6462304.3],[403908.0,6462289.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_le_bois_3', '{"type":"MultiPolygon","coordinates":[[[[403094.2,6461677.4],[403133.0,6461689.9],[403139.1,6461691.8],[403137.5,6461618.2],[403137.1,6461614.1],[403131.9,6461613.2],[403118.8,6461610.5],[403115.6,6461609.9],[403112.8,6461609.3],[403108.4,6461608.2],[403104.3,6461606.8],[403100.6,6461604.9],[403092.4,6461601.8],[403086.3,6461600.5],[403094.2,6461677.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_roseaux', '{"type":"MultiPolygon","coordinates":[[[[403170.2,6461751.9],[403156.7,6461753.2],[403154.4,6461758.4],[403154.8,6461766.0],[403139.5,6461766.4],[403141.3,6461831.7],[403144.0,6461833.5],[403172.3,6461830.5],[403170.2,6461751.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('berret_3', '{"type":"MultiPolygon","coordinates":[[[[402951.6,6463289.4],[402986.4,6463287.1],[402983.3,6463248.0],[402981.5,6463247.3],[402981.1,6463241.7],[402982.0,6463241.7],[402975.8,6463158.2],[402974.0,6463156.0],[402972.0,6463156.2],[402944.9,6463176.1],[402945.7,6463186.5],[402943.7,6463186.6],[402951.6,6463289.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_virage', '{"type":"MultiPolygon","coordinates":[[[[403033.6,6461872.7],[403040.3,6461896.8],[403041.2,6461896.7],[403053.7,6461942.8],[403062.8,6461939.6],[403105.5,6461928.0],[403086.1,6461856.2],[403058.0,6461860.8],[403052.7,6461865.0],[403038.4,6461870.7],[403036.8,6461871.7],[403033.6,6461872.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('rodites_2_CS', '{"type":"MultiPolygon","coordinates":[[[[405725.0,6461515.5],[405721.4,6461502.8],[405634.8,6461525.5],[405640.6,6461537.7],[405725.0,6461515.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_le_bois_4', '{"type":"MultiPolygon","coordinates":[[[[403188.8,6461697.8],[403187.4,6461620.4],[403140.4,6461614.7],[403142.0,6461692.7],[403149.6,6461694.8],[403156.9,6461696.0],[403161.2,6461696.6],[403188.8,6461697.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('rodites_2_M', '{"type":"MultiPolygon","coordinates":[[[[405721.4,6461502.8],[405712.5,6461472.1],[405710.3,6461472.7],[405699.6,6461473.2],[405685.4,6461462.1],[405682.0,6461460.7],[405614.5,6461478.7],[405632.1,6461523.3],[405633.4,6461522.9],[405634.8,6461525.5],[405721.4,6461502.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('rodites_1', '{"type":"MultiPolygon","coordinates":[[[[405639.4,6461465.2],[405612.8,6461366.1],[405591.3,6461372.6],[405582.7,6461380.7],[405587.2,6461396.8],[405588.1,6461396.5],[405595.3,6461423.4],[405596.3,6461423.1],[405609.7,6461473.4],[405639.4,6461465.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_le_bois_2', '{"type":"MultiPolygon","coordinates":[[[[403031.8,6461657.1],[403094.2,6461677.4],[403086.3,6461600.5],[403068.7,6461590.8],[403043.2,6461574.2],[403032.8,6461569.5],[403025.1,6461566.5],[403022.5,6461565.8],[403031.8,6461657.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_le_bois_1', '{"type":"MultiPolygon","coordinates":[[[[402978.7,6461639.0],[403011.2,6461650.3],[403031.8,6461657.1],[403022.5,6461565.8],[403015.3,6461566.0],[402971.8,6461572.7],[402978.7,6461639.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('pibran_2', '{"type":"MultiPolygon","coordinates":[[[[403971.9,6462186.1],[403970.1,6462197.8],[403969.2,6462204.9],[403968.9,6462209.7],[403968.2,6462218.7],[403967.0,6462241.0],[403986.1,6462241.7],[403986.6,6462228.5],[403986.9,6462221.5],[403987.2,6462217.6],[403987.7,6462213.0],[403988.5,6462206.4],[403989.5,6462198.5],[403990.3,6462193.0],[403992.2,6462181.9],[403995.2,6462168.4],[404002.2,6462137.9],[403984.5,6462128.3],[403971.9,6462186.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_planteyrot_2', '{"type":"MultiPolygon","coordinates":[[[[403556.5,6462049.5],[403556.8,6462054.5],[403603.2,6462051.5],[403603.4,6462046.4],[403556.5,6462049.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('berret_1', '{"type":"MultiPolygon","coordinates":[[[[402801.7,6463391.1],[402922.4,6463383.3],[402916.8,6463301.4],[402834.2,6463308.4],[402836.7,6463347.5],[402798.7,6463349.9],[402801.7,6463391.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_centre_3', '{"type":"MultiPolygon","coordinates":[[[[403425.5,6461971.1],[403424.4,6461971.4],[403421.2,6461977.3],[403426.5,6462046.4],[403427.4,6462046.5],[403427.8,6462050.5],[403426.0,6462051.1],[403428.4,6462083.3],[403429.5,6462083.6],[403429.8,6462088.6],[403435.0,6462088.4],[403425.5,6461971.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_planteyrot_3', '{"type":"MultiPolygon","coordinates":[[[[403556.7,6462034.1],[403605.2,6462030.6],[403606.5,6462013.8],[403606.4,6462005.9],[403602.7,6462001.9],[403595.3,6461998.3],[403592.8,6461998.1],[403556.1,6462001.2],[403555.9,6462005.0],[403556.7,6462034.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_centre_1', '{"type":"MultiPolygon","coordinates":[[[[403225.5,6461901.5],[403234.9,6461898.2],[403216.9,6461840.4],[403215.2,6461840.3],[403214.5,6461838.6],[403206.9,6461838.8],[403225.5,6461901.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_centre_2', '{"type":"MultiPolygon","coordinates":[[[[403276.8,6461970.8],[403283.1,6461970.8],[403269.3,6461905.9],[403248.5,6461842.1],[403241.9,6461841.8],[403249.9,6461866.0],[403253.4,6461876.2],[403261.9,6461903.1],[403276.8,6461970.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_le_bois_6', '{"type":"MultiPolygon","coordinates":[[[[403206.0,6461623.3],[403206.1,6461697.4],[403230.7,6461700.4],[403250.1,6461701.4],[403255.3,6461701.2],[403279.1,6461696.4],[403279.0,6461625.2],[403230.7,6461626.0],[403206.0,6461623.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_triau_bas_2', '{"type":"MultiPolygon","coordinates":[[[[402917.8,6462243.2],[402899.0,6462243.6],[402898.2,6462308.1],[402925.3,6462307.3],[402925.9,6462248.1],[402917.8,6462243.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_triau_haut_1', '{"type":"MultiPolygon","coordinates":[[[[402925.4,6462135.7],[402883.5,6462136.5],[402882.3,6462237.8],[402914.5,6462236.5],[402925.3,6462230.5],[402925.8,6462192.4],[402924.7,6462192.4],[402925.4,6462135.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_les_chevres_2', '{"type":"MultiPolygon","coordinates":[[[[402917.8,6461703.6],[402921.0,6461756.5],[403008.3,6461743.0],[403014.0,6461688.9],[402917.8,6461703.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_la_gravette', '{"type":"MultiPolygon","coordinates":[[[[403086.6,6461680.7],[403064.7,6461674.0],[403075.2,6461757.3],[403096.2,6461756.0],[403086.6,6461680.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_le_bois_5', '{"type":"MultiPolygon","coordinates":[[[[403188.8,6461697.8],[403206.1,6461697.4],[403206.0,6461623.3],[403187.4,6461620.4],[403188.8,6461697.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_planteyrot_1', '{"type":"MultiPolygon","coordinates":[[[[403557.5,6462070.5],[403558.1,6462074.9],[403558.6,6462076.3],[403582.0,6462074.9],[403598.2,6462069.4],[403601.8,6462067.9],[403557.5,6462070.5]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('piece_de_la_tour', '{"type":"MultiPolygon","coordinates":[[[[405717.1,6460023.2],[405765.7,6460015.5],[405865.3,6460017.9],[405881.0,6460018.6],[405932.9,6460024.1],[405936.6,6460023.8],[405939.5,6460022.6],[405947.8,6459966.7],[405918.9,6459929.9],[405917.8,6459931.0],[405916.9,6459934.9],[405917.1,6459941.0],[405780.8,6459913.9],[405694.9,6459896.7],[405717.1,6460023.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_2', '{"type":"MultiPolygon","coordinates":[[[[404011.7,6459914.0],[404016.5,6459914.0],[404039.0,6459903.0],[404221.0,6459798.0],[404166.9,6459745.3],[403961.1,6459860.3],[403959.5,6459864.2],[404011.7,6459914.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('petit_batailley_4', '{"type":"MultiPolygon","coordinates":[[[[404020.0,6459921.8],[404072.5,6459971.8],[404077.1,6459970.1],[404082.7,6459967.2],[404281.2,6459854.7],[404224.3,6459801.7],[404021.1,6459917.2],[404020.4,6459918.7],[404020.0,6459921.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('forts_de_latour_CS_sud', '{"type":"MultiPolygon","coordinates":[[[[405665.4,6460220.0],[405741.2,6460211.5],[405735.7,6460179.5],[405661.0,6460187.9],[405665.4,6460220.0]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('roca_3', '{"type":"MultiPolygon","coordinates":[[[[403474.9,6460039.3],[403532.2,6460012.9],[403556.1,6459999.5],[403579.3,6459987.8],[403582.4,6459984.5],[403575.6,6459970.6],[403538.7,6459917.4],[403441.2,6459973.7],[403474.9,6460039.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('dardenans_1', '{"type":"MultiPolygon","coordinates":[[[[404713.8,6460964.2],[404741.7,6460957.5],[404737.1,6460865.0],[404735.2,6460857.1],[404716.9,6460857.4],[404699.3,6460861.3],[404692.9,6460864.9],[404690.7,6460868.2],[404713.8,6460964.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('dardenans_2', '{"type":"MultiPolygon","coordinates":[[[[404745.6,6460956.9],[404761.3,6460952.0],[404777.1,6460944.6],[404798.0,6460931.7],[404827.6,6460913.2],[404820.0,6460812.3],[404737.9,6460814.6],[404742.3,6460911.0],[404745.6,6460956.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('dardenans_3', '{"type":"MultiPolygon","coordinates":[[[[404737.7,6460811.6],[404736.8,6460797.3],[404737.7,6460797.1],[404735.1,6460761.6],[404736.9,6460758.5],[404815.8,6460756.1],[404819.9,6460809.7],[404737.7,6460811.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('les_parcs_CS', '{"type":"MultiPolygon","coordinates":[[[[404690.7,6460588.9],[404702.3,6460595.4],[404704.0,6460659.4],[404691.8,6460661.4],[404690.2,6460621.9],[404690.7,6460588.9]]],[[[404711.5,6460657.7],[404717.3,6460656.4],[404716.9,6460605.4],[404711.0,6460601.8],[404711.5,6460657.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('terre_noire', '{"type":"MultiPolygon","coordinates":[[[[404450.6,6460637.7],[404431.2,6460726.5],[404433.3,6460727.2],[404452.3,6460638.2],[404450.6,6460637.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grande_lande_2', '{"type":"MultiPolygon","coordinates":[[[[404518.1,6461192.2],[404505.6,6461201.8],[404551.2,6461247.7],[404564.8,6461261.6],[404577.4,6461278.7],[404582.6,6461288.8],[404596.5,6461281.0],[404580.2,6461256.2],[404556.0,6461230.5],[404539.3,6461214.3],[404518.1,6461192.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('artigues_centre_5', '{"type":"MultiPolygon","coordinates":[[[[403439.2,6462026.1],[403463.7,6462025.1],[403463.8,6462030.7],[403467.8,6462030.5],[403469.1,6462085.5],[403444.6,6462087.5],[403439.2,6462026.1]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('berret_2', '{"type":"MultiPolygon","coordinates":[[[[402922.4,6463383.3],[403007.7,6463378.3],[403004.1,6463293.4],[402916.8,6463301.4],[402922.4,6463383.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('saucina', '{"type":"MultiPolygon","coordinates":[[[[404870.1,6462670.8],[404871.7,6462672.9],[404945.6,6462721.2],[404968.1,6462735.3],[404977.7,6462740.5],[405015.5,6462752.1],[405036.9,6462649.4],[404987.8,6462646.2],[404962.6,6462644.6],[404940.7,6462643.4],[404879.0,6462630.3],[404875.2,6462631.9],[404871.7,6462652.1],[404870.1,6462670.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gaillottes_7', '{"type":"MultiPolygon","coordinates":[[[[404522.8,6460764.7],[404539.5,6460765.8],[404539.5,6460766.6],[404552.1,6460766.9],[404570.9,6460665.0],[404558.9,6460664.7],[404542.3,6460663.1],[404522.8,6460764.7]]],[[[404556.2,6460766.8],[404555.6,6460758.7],[404573.5,6460665.2],[404578.1,6460665.3],[404582.8,6460765.4],[404556.2,6460766.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gaillottes_6', '{"type":"MultiPolygon","coordinates":[[[[404582.9,6460763.9],[404588.9,6460763.5],[404584.4,6460667.1],[404578.3,6460667.2],[404582.9,6460763.9]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gaillottes_5', '{"type":"MultiPolygon","coordinates":[[[[404589.0,6460764.5],[404596.7,6460763.7],[404596.7,6460764.8],[404601.9,6460764.6],[404596.9,6460665.0],[404584.3,6460666.0],[404589.0,6460764.5]]],[[[404606.2,6460763.8],[404618.7,6460762.2],[404613.9,6460665.6],[404601.3,6460665.1],[404603.7,6460723.3],[404605.2,6460752.5],[404606.2,6460763.8]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gaillottes_4', '{"type":"MultiPolygon","coordinates":[[[[404618.7,6460762.2],[404622.9,6460761.6],[404619.6,6460712.8],[404617.9,6460665.9],[404613.9,6460665.6],[404616.0,6460722.3],[404618.7,6460762.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gaillottes_3', '{"type":"MultiPolygon","coordinates":[[[[404622.9,6460761.6],[404650.6,6460757.9],[404657.6,6460757.7],[404654.2,6460667.3],[404617.9,6460665.9],[404619.6,6460712.8],[404622.9,6460761.6]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gaillottes_2', '{"type":"MultiPolygon","coordinates":[[[[404657.6,6460757.7],[404667.9,6460756.5],[404664.3,6460667.2],[404654.2,6460667.3],[404657.6,6460757.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('gaillottes_1', '{"type":"MultiPolygon","coordinates":[[[[404667.9,6460756.5],[404676.9,6460756.6],[404673.5,6460667.3],[404664.3,6460667.2],[404667.9,6460756.5]]],[[[404680.4,6460756.2],[404676.9,6460666.8],[404698.9,6460665.2],[404702.4,6460754.8],[404680.4,6460756.2]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('parc_de_pelon', '{"type":"MultiPolygon","coordinates":[[[[402926.4,6462041.4],[403007.4,6462021.4],[403012.0,6462086.2],[403005.7,6462087.8],[402936.0,6462090.1],[402933.9,6462074.8],[402931.6,6462056.0],[402926.4,6462041.4]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('lespret', '{"type":"MultiPolygon","coordinates":[[[[406170.0,6460346.3],[406179.6,6460203.7],[406185.2,6460200.6],[406260.5,6460214.3],[406274.6,6460221.3],[406284.5,6460228.0],[406292.4,6460237.7],[406298.6,6460246.0],[406304.2,6460255.5],[406307.3,6460266.3],[406305.0,6460278.7],[406275.9,6460343.0],[406170.0,6460346.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('roca_4', '{"type":"MultiPolygon","coordinates":[[[[403338.6,6460025.3],[403301.8,6459951.7],[403236.9,6459989.3],[403275.3,6460063.3],[403338.6,6460025.3]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;

INSERT INTO vigne_parcelle (slug, geometrie) VALUES ('grande_lande_3', '{"type":"MultiPolygon","coordinates":[[[[404572.9,6460367.7],[404577.2,6460371.3],[404580.3,6460356.6],[404582.6,6460344.2],[404585.3,6460326.2],[404587.7,6460302.1],[404589.5,6460276.3],[404590.5,6460258.3],[404585.7,6460257.8],[404583.5,6460288.7],[404582.4,6460303.2],[404581.9,6460310.6],[404578.5,6460338.2],[404575.4,6460355.2],[404572.9,6460367.7]]]]}')
ON CONFLICT (slug) DO UPDATE SET geometrie = EXCLUDED.geometrie;


COMMIT;
