<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241206104230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE product_id_seq1 CASCADE');
        $this->addSql('DROP SEQUENCE dotation.article_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE dotation.couleur_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE dotation.taille_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE dotation.association_article_couleurs_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE dotation.association_taille_artilces_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE dotation.type_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE association_tailles_article_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE article (id INT NOT NULL, nom VARCHAR(255) NOT NULL, prix INT NOT NULL, reference VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, nom_type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE association_couleurs_article (id INT NOT NULL, id_article INT NOT NULL, nom_couleur VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE association_tailles_article (id INT NOT NULL, id_article INT NOT NULL, nom_taille VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE couleur (id INT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE taille (id INT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE type (id INT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE dotation.association_tailles_artilce DROP CONSTRAINT association_taille_artilces_article_fk');
        $this->addSql('ALTER TABLE dotation.association_tailles_artilce DROP CONSTRAINT association_taille_artilces_taille_fk');
        $this->addSql('ALTER TABLE dotation.association_couleurs_article DROP CONSTRAINT association_article_couleurs_couleur_fk');
        $this->addSql('ALTER TABLE dotation.association_couleurs_article DROP CONSTRAINT association_article_couleurs_article_fk');
        $this->addSql('ALTER TABLE dotation.article DROP CONSTRAINT article_type_fk');
        $this->addSql('DROP TABLE dotation.couleur');
        $this->addSql('DROP TABLE dotation.type');
        $this->addSql('DROP TABLE r&d.limite_petiolle');
        $this->addSql('DROP TABLE dotation.association_tailles_artilce');
        $this->addSql('DROP TABLE r&d.srdv');
        $this->addSql('DROP TABLE dotation.association_couleurs_article');
        $this->addSql('DROP TABLE dotation.article');
        $this->addSql('DROP TABLE dotation.taille');
        $this->addSql('ALTER TABLE product ALTER id DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SCHEMA r&d');
        $this->addSql('CREATE SCHEMA dotation');
        $this->addSql('DROP SEQUENCE association_tailles_article_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE product_id_seq1 INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE dotation.article_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE dotation.couleur_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE dotation.taille_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE dotation.association_article_couleurs_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE dotation.association_taille_artilces_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE dotation.type_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE dotation.couleur (id SERIAL NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX couleur_unique ON dotation.couleur (nom)');
        $this->addSql('CREATE TABLE dotation.type (id SERIAL NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX type_unique ON dotation.type (nom)');
        $this->addSql('CREATE TABLE r&d.limite_petiolle ("Millésime" VARCHAR(50) DEFAULT NULL, "Secteur" VARCHAR(50) DEFAULT NULL, "#" VARCHAR(50) DEFAULT NULL, "Validité" VARCHAR(50) DEFAULT NULL, "Code Client" VARCHAR(50) DEFAULT NULL, "Nom Courant" VARCHAR(50) DEFAULT NULL, "Dossier" VARCHAR(50) DEFAULT NULL, "Numéro" VARCHAR(50) DEFAULT NULL, "Date" VARCHAR(50) DEFAULT NULL, "Demande" VARCHAR(50) DEFAULT NULL, "Parcelle" VARCHAR(50) DEFAULT NULL, "Irrigation" VARCHAR(50) DEFAULT NULL, "Numéro Parcelle" VARCHAR(50) DEFAULT NULL, "Exploitant" VARCHAR(50) DEFAULT NULL, "Identification" VARCHAR(50) DEFAULT NULL, "Cépage" VARCHAR(50) DEFAULT NULL, "Porte Greffe" VARCHAR(50) DEFAULT NULL, "Commentaire SRDV" VARCHAR(50) DEFAULT NULL, "Stade" VARCHAR(50) DEFAULT NULL, "Ntt" VARCHAR(50) DEFAULT NULL, "P" VARCHAR(50) DEFAULT NULL, "K" VARCHAR(50) DEFAULT NULL, "Ca" VARCHAR(50) DEFAULT NULL, "Mg" VARCHAR(50) DEFAULT NULL, "Na" INT DEFAULT NULL, "Fe" INT DEFAULT NULL, "Mn" INT DEFAULT NULL, "Cu" INT DEFAULT NULL, "Zn" INT DEFAULT NULL, "B" INT DEFAULT NULL, "PS" VARCHAR(50) DEFAULT NULL, "Nbre" VARCHAR(50) DEFAULT NULL, "K/Mg" VARCHAR(50) DEFAULT NULL, "PS 100pet" VARCHAR(50) DEFAULT NULL, "N mg/100pet" INT DEFAULT NULL, "P mg/100pet" INT DEFAULT NULL, "K mg/100pet" INT DEFAULT NULL, "Ca mg/100pet" INT DEFAULT NULL, "Mg mg/100pet" INT DEFAULT NULL, "Na µg/100pet" INT DEFAULT NULL, "Fe µg/100pet" INT DEFAULT NULL, "Mn µg/100pet" INT DEFAULT NULL, "Cu µg/100pet" INT DEFAULT NULL, "Zn µg/100pet" INT DEFAULT NULL, "B µg/100pet" INT DEFAULT NULL, "10P/N+10P+K" VARCHAR(50) DEFAULT NULL, "K/N+10P+K" VARCHAR(50) DEFAULT NULL, "N /N+10P+K" VARCHAR(50) DEFAULT NULL, "K/K+Ca+Mg" VARCHAR(50) DEFAULT NULL, "Ca/K+Ca+Mg" VARCHAR(50) DEFAULT NULL, "Mg/K+Ca+Mg" VARCHAR(50) DEFAULT NULL, "PS_100PET" VARCHAR(50) DEFAULT NULL, "Ca_mg" INT DEFAULT NULL, "K_mg" INT DEFAULT NULL, "Mg_mg" INT DEFAULT NULL, "Ntt_mg" INT DEFAULT NULL, "P_mg" INT DEFAULT NULL, "Na_µ" INT DEFAULT NULL, "Fe_µ" INT DEFAULT NULL, "Mn_µ" INT DEFAULT NULL, "Cu_µ" INT DEFAULT NULL, "Zn_µ" INT DEFAULT NULL, "B_µ" INT DEFAULT NULL, "K / Mg" VARCHAR(50) DEFAULT NULL, "N/(N+10*P+K)" VARCHAR(50) DEFAULT NULL, "P*10/(N+10*P+K)" VARCHAR(50) DEFAULT NULL, "K/(N+10*P+K)" VARCHAR(50) DEFAULT NULL, "K/(K+Ca+Mg)" VARCHAR(50) DEFAULT NULL, "Ca/(K+Ca+Mg)" VARCHAR(50) DEFAULT NULL, "Mg/(K+Ca+Mg)" VARCHAR(50) DEFAULT NULL, "Colonne1" VARCHAR(50) DEFAULT NULL)');
        $this->addSql('CREATE TABLE dotation.association_tailles_artilce (id SERIAL NOT NULL, id_article INT NOT NULL, nom_taille VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A4FEEA25DCA7A716 ON dotation.association_tailles_artilce (id_article)');
        $this->addSql('CREATE INDEX IDX_A4FEEA25F69F146F ON dotation.association_tailles_artilce (nom_taille)');
        $this->addSql('CREATE TABLE r&d.srdv ("#" INT DEFAULT NULL, "Visibilité" VARCHAR(255) DEFAULT NULL, "Code Client" INT DEFAULT NULL, "Nom Courant" VARCHAR(255) DEFAULT NULL, "Code Postal (Site)" INT DEFAULT NULL, "Code Postal (Fact)" INT DEFAULT NULL, "Dossier" VARCHAR(255) DEFAULT NULL, "Numéro" INT DEFAULT NULL, "Date" VARCHAR(255) DEFAULT NULL, "Demande" VARCHAR(255) DEFAULT NULL, "Matrice" VARCHAR(255) DEFAULT NULL, "Millésime" VARCHAR(255) DEFAULT NULL, "Identification" VARCHAR(255) DEFAULT NULL, "Commentaires" VARCHAR(255) DEFAULT NULL, "Cépage" VARCHAR(255) DEFAULT NULL, "Commentaires_1" VARCHAR(255) DEFAULT NULL, "Culture" VARCHAR(255) DEFAULT NULL, "Culture n" VARCHAR(255) DEFAULT NULL, "Culture n-1" VARCHAR(255) DEFAULT NULL, "Dernier fertilisant" VARCHAR(255) DEFAULT NULL, "Dernier fertilisant appliqué" VARCHAR(255) DEFAULT NULL, "Exploitant" VARCHAR(255) DEFAULT NULL, "Nature" VARCHAR(255) DEFAULT NULL, "Nombre" INT DEFAULT NULL, "Nbr de sarments-pétioles" VARCHAR(255) DEFAULT NULL, "Nbr sarments-petioles  profondeur sol" INT DEFAULT NULL, "Numero parcelle" INT DEFAULT NULL, "Parcelle" VARCHAR(255) DEFAULT NULL, "Poids frais (en grammes)" VARCHAR(255) DEFAULT NULL, "Porte-greffe" VARCHAR(255) DEFAULT NULL, "Stade phénologique" VARCHAR(255) DEFAULT NULL, "Type de sol" VARCHAR(255) DEFAULT NULL, "Nbre paramètres" INT DEFAULT NULL, "10P/N+10P+K" VARCHAR(255) DEFAULT NULL, "10P/N+10P+K Limbes" VARCHAR(255) DEFAULT NULL, "ARGILES" VARCHAR(255) DEFAULT NULL, "AZOTE TOT FLASH" VARCHAR(255) DEFAULT NULL, "AZOTE TOTAL NIR SRDV" VARCHAR(255) DEFAULT NULL, "B µg/100 PET" VARCHAR(255) DEFAULT NULL, "B µg/limbe" VARCHAR(255) DEFAULT NULL, "BACT G+ MORTES CYTO" INT DEFAULT NULL, "BACT G+ TOT CYTO" INT DEFAULT NULL, "BACT G+ VMI CYTO" INT DEFAULT NULL, "BACT G+ VV CYTO" INT DEFAULT NULL, "BACT G- MORTES CYTO" INT DEFAULT NULL, "BACT G- TOT CYTO" INT DEFAULT NULL, "BACT G- VMI CYTO" INT DEFAULT NULL, "BACT G- VV CYTO" INT DEFAULT NULL, "BACT"." MORTES CYTO" INT DEFAULT NULL, "BACT"." TOT CYTO" INT DEFAULT NULL, "BACT"." VMI CYTO" INT DEFAULT NULL, "BACT"." VV CYTO" INT DEFAULT NULL, "BORE MP SRDV" VARCHAR(255) DEFAULT NULL, "C/N SOLS" VARCHAR(255) DEFAULT NULL, "CALCIUM EDTA MP" VARCHAR(255) DEFAULT NULL, "CALCIUM MP SRDV" VARCHAR(255) DEFAULT NULL, "CARBONE TOTAL NIR" VARCHAR(255) DEFAULT NULL, "CEC NIR" VARCHAR(255) DEFAULT NULL, "CHAMP"." MORTS CYTO" INT DEFAULT NULL, "CHAMP"." TOT CYTO" INT DEFAULT NULL, "CHAMP"." VMI CYTO" INT DEFAULT NULL, "CHAMP"." VV CYTO" INT DEFAULT NULL, "CHAMPI/BACT" VARCHAR(255) DEFAULT NULL, "CUIVRE EDTA MP" VARCHAR(255) DEFAULT NULL, "CUIVRE MP SRDV" VARCHAR(255) DEFAULT NULL, "CUIVRE mp" VARCHAR(255) DEFAULT NULL, "Ca mg/100 PET" VARCHAR(255) DEFAULT NULL, "Ca mg/limbe" VARCHAR(255) DEFAULT NULL, "Ca/CEC SOLS" VARCHAR(255) DEFAULT NULL, "Ca/K+Ca+Mg" VARCHAR(255) DEFAULT NULL, "CaO CALC SOLS" VARCHAR(255) DEFAULT NULL, "Calcaire total NIR" VARCHAR(255) DEFAULT NULL, "Cu µg/100 PET" VARCHAR(255) DEFAULT NULL, "Cu µg/limbe" VARCHAR(255) DEFAULT NULL, "FER EDTA MP" VARCHAR(255) DEFAULT NULL, "FER MP SRDV" INT DEFAULT NULL, "Fe µg/100 PET" VARCHAR(255) DEFAULT NULL, "Fe µg/limbe" VARCHAR(255) DEFAULT NULL, "HUMIDITE" VARCHAR(255) DEFAULT NULL, "K mg/100 PET" VARCHAR(255) DEFAULT NULL, "K mg/limbe" VARCHAR(255) DEFAULT NULL, "K+Mg+Ca/CEC SOLS" VARCHAR(255) DEFAULT NULL, "K/CEC SOLS" VARCHAR(255) DEFAULT NULL, "K/K+Ca+Mg" VARCHAR(255) DEFAULT NULL, "K/Mg" VARCHAR(255) DEFAULT NULL, "K/Mg SOLS" VARCHAR(255) DEFAULT NULL, "K/N+10P+K" VARCHAR(255) DEFAULT NULL, "K/N+10P+K limbes" VARCHAR(255) DEFAULT NULL, "K2O CALC SOLS" VARCHAR(255) DEFAULT NULL, "LIMONS FINS" VARCHAR(255) DEFAULT NULL, "LIMONS GROSSIERS" VARCHAR(255) DEFAULT NULL, "MAGNESIUM EDTA MP" VARCHAR(255) DEFAULT NULL, "MAGNESIUM MP SRDV" VARCHAR(255) DEFAULT NULL, "MANGANESE EDTA MP" VARCHAR(255) DEFAULT NULL, "MANGANESE MP SRDV" VARCHAR(255) DEFAULT NULL, "MICROORG"." MORTS CYTO" VARCHAR(255) DEFAULT NULL, "MICROORG"." TOTAUX CYTO" INT DEFAULT NULL, "MICROORG"." VIVANTS CYTO" VARCHAR(255) DEFAULT NULL, "MICROORG"." VNC CYTO" VARCHAR(255) DEFAULT NULL, "MO SOLS" VARCHAR(255) DEFAULT NULL, "Mg mg/100 PET" VARCHAR(255) DEFAULT NULL, "Mg mg/limbe" VARCHAR(255) DEFAULT NULL, "Mg/CEC SOLS" VARCHAR(255) DEFAULT NULL, "Mg/K+Ca+Mg" VARCHAR(255) DEFAULT NULL, "MgO CALC SOLS" VARCHAR(255) DEFAULT NULL, "Mn µg/100 PET" VARCHAR(255) DEFAULT NULL, "Mn µg/limbe" VARCHAR(255) DEFAULT NULL, "N mg/100 PET" VARCHAR(255) DEFAULT NULL, "N mg/limbe" VARCHAR(255) DEFAULT NULL, "N/N+10P+K" VARCHAR(255) DEFAULT NULL, "N/N+10P+K limbes" VARCHAR(255) DEFAULT NULL, "NOMBRE DE PETIOLES SRDV" INT DEFAULT NULL, "Na µg/100 PET" VARCHAR(255) DEFAULT NULL, "Na µg/limbe" VARCHAR(255) DEFAULT NULL, "P mg/100 PET" VARCHAR(255) DEFAULT NULL, "P mg/limbe" VARCHAR(255) DEFAULT NULL, "PHOSPHORE MP SRDV" VARCHAR(255) DEFAULT NULL, "POIDS SEC 1 LIMBE" VARCHAR(255) DEFAULT NULL, "POIDS SEC 100PET" VARCHAR(255) DEFAULT NULL, "POIDS SEC SRDV" VARCHAR(255) DEFAULT NULL, "POTASSIUM EDTA MP" VARCHAR(255) DEFAULT NULL, "POTASSIUM MP SRDV" VARCHAR(255) DEFAULT NULL, "PROTISTES MORTS CYTO" INT DEFAULT NULL, "PROTISTES TOT CYTO" INT DEFAULT NULL, "PROTISTES VMI CYTO" INT DEFAULT NULL, "PROTISTES VV CYTO" INT DEFAULT NULL, "REFUS" VARCHAR(255) DEFAULT NULL, "SABLES FINS" VARCHAR(255) DEFAULT NULL, "SABLES GROSSIERS" VARCHAR(255) DEFAULT NULL, "SODIUM MP SRDV" INT DEFAULT NULL, "TAUX ACTIVITE BACTERIES" VARCHAR(255) DEFAULT NULL, "TAUX ACTIVITE CHAMPIGNONS" VARCHAR(255) DEFAULT NULL, "TAUX ACTIVITE MICROORGANISMES" VARCHAR(255) DEFAULT NULL, "TAUX ACTIVITE PROTISTES" VARCHAR(255) DEFAULT NULL, "ZINC EDTA MP" VARCHAR(255) DEFAULT NULL, "ZINC MP SRDV" INT DEFAULT NULL, "Zn µg/100 PET" VARCHAR(255) DEFAULT NULL, "Zn µg/limbe" VARCHAR(255) DEFAULT NULL, "pH Eau - pH KCl" VARCHAR(255) DEFAULT NULL, "pH Eau NIR" VARCHAR(255) DEFAULT NULL, "pH KCl NIR" VARCHAR(255) DEFAULT NULL)');
        $this->addSql('CREATE TABLE dotation.association_couleurs_article (id SERIAL NOT NULL, id_article INT NOT NULL, nom_couleur VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C88CFBB2C9828C2D ON dotation.association_couleurs_article (nom_couleur)');
        $this->addSql('CREATE INDEX IDX_C88CFBB2DCA7A716 ON dotation.association_couleurs_article (id_article)');
        $this->addSql('CREATE TABLE dotation.article (id SERIAL NOT NULL, nom_type VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prix VARCHAR(255) NOT NULL, reference VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FCE9852F7E0E9D47 ON dotation.article (nom_type)');
        $this->addSql('CREATE TABLE dotation.taille (id SERIAL NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX taille_unique ON dotation.taille (nom)');
        $this->addSql('ALTER TABLE dotation.association_tailles_artilce ADD CONSTRAINT association_taille_artilces_article_fk FOREIGN KEY (id_article) REFERENCES dotation.article (id) ON UPDATE CASCADE ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE dotation.association_tailles_artilce ADD CONSTRAINT association_taille_artilces_taille_fk FOREIGN KEY (nom_taille) REFERENCES dotation.taille (nom) ON UPDATE CASCADE ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE dotation.association_couleurs_article ADD CONSTRAINT association_article_couleurs_couleur_fk FOREIGN KEY (nom_couleur) REFERENCES dotation.couleur (nom) ON UPDATE CASCADE ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE dotation.association_couleurs_article ADD CONSTRAINT association_article_couleurs_article_fk FOREIGN KEY (id_article) REFERENCES dotation.article (id) ON UPDATE CASCADE ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE dotation.article ADD CONSTRAINT article_type_fk FOREIGN KEY (nom_type) REFERENCES dotation.type (nom) ON UPDATE CASCADE ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE association_couleurs_article');
        $this->addSql('DROP TABLE association_tailles_article');
        $this->addSql('DROP TABLE couleur');
        $this->addSql('DROP TABLE taille');
        $this->addSql('DROP TABLE type');
        $this->addSql('CREATE SEQUENCE product_id_seq');
        $this->addSql('SELECT setval(\'product_id_seq\', (SELECT MAX(id) FROM product))');
        $this->addSql('ALTER TABLE product ALTER id SET DEFAULT nextval(\'product_id_seq\')');
    }
}
