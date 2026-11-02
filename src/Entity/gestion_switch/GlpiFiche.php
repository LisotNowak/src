<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\GlpiFicheRepository;
use Doctrine\ORM\Mapping as ORM;

/** Fiche GLPI d'un appareil du réseau, par adresse MAC (remplacées à chaque collecte GLPI réussie). */
#[ORM\Entity(repositoryClass: GlpiFicheRepository::class)]
#[ORM\Table(name: 'glpi_fiche', schema: 'gestion_switch')]
class GlpiFiche
{
    #[ORM\Id]
    #[ORM\Column(columnDefinition: 'MACADDR NOT NULL')]
    private string $mac = '';

    /** Computer, NetworkEquipment, Phone… */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $itemtype = null;

    #[ORM\Column(nullable: true)]
    private ?int $glpiId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $typeLibelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $modele = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroSerie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $utilisateur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $inventaire = null;

    /** port réseau GLPI portant la MAC */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $port = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url = null;

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $vuLe = null;

    public function getMac(): string
    {
        return $this->mac;
    }

    public function getItemtype(): ?string
    {
        return $this->itemtype;
    }

    public function getGlpiId(): ?int
    {
        return $this->glpiId;
    }

    public function getTypeLibelle(): ?string
    {
        return $this->typeLibelle;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function getUtilisateur(): ?string
    {
        return $this->utilisateur;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function getInventaire(): ?string
    {
        return $this->inventaire;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getVuLe(): ?\DateTimeImmutable
    {
        return $this->vuLe;
    }
}
