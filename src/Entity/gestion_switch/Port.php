<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\PortRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Port d'un switch (physique ou agrégat), état à la dernière collecte réussie du switch.
 * Écrit par App\Service\Aruba\Store, qui enregistre aussi chaque changement (PortEvenement).
 */
#[ORM\Entity(repositoryClass: PortRepository::class)]
#[ORM\Table(name: 'port', schema: 'gestion_switch')]
#[ORM\UniqueConstraint(name: 'port_equipement_id_nom_key', columns: ['equipement_id', 'nom'])]
class Port
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    /** 1/1/12, Trk1, lag1… */
    #[ORM\Column(length: 30)]
    private string $nom = '';

    /** system (port physique) | lag (agrégat) */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /** up | down (désactivé) */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $admin = null;

    /** up | down */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $lien = null;

    /** bit/s */
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $vitesse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $duplex = null;

    /** rj45 | sfp */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $connecteur = null;

    /** module SFP inséré */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $module = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $vlanMode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $vlanNatif = null;

    /** « 10,20,30 » */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $vlansTagues = null;

    /** access | uplink | ap | ext | lag */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $role = null;

    /** agrégat dont le port est membre */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $agregat = null;

    /** membres d'un agrégat, séparés par des virgules */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $membres = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $nbMac = 0;

    /** UTC : lien dans cet état depuis (au plus tôt : 1re collecte détaillée, le 29/10/2026) */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $etatDepuis = null;

    /** UTC : dernière collecte */
    #[ORM\Column]
    private ?\DateTimeImmutable $vuLe = null;

    /** @var Collection<int, PortVoisin> voisins LLDP */
    #[ORM\OneToMany(mappedBy: 'port', targetEntity: PortVoisin::class)]
    private Collection $voisins;

    public function __construct()
    {
        $this->voisins = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAdmin(): ?string
    {
        return $this->admin;
    }

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function getVitesse(): ?string
    {
        return $this->vitesse;
    }

    public function getDuplex(): ?string
    {
        return $this->duplex;
    }

    public function getConnecteur(): ?string
    {
        return $this->connecteur;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function getVlanMode(): ?string
    {
        return $this->vlanMode;
    }

    public function getVlanNatif(): ?string
    {
        return $this->vlanNatif;
    }

    /** @return string[] */
    public function getVlansTagues(): array
    {
        return $this->vlansTagues ? explode(',', $this->vlansTagues) : [];
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getAgregat(): ?string
    {
        return $this->agregat;
    }

    /** @return string[] */
    public function getMembres(): array
    {
        return $this->membres ? explode(',', $this->membres) : [];
    }

    public function getNbMac(): int
    {
        return $this->nbMac;
    }

    public function getEtatDepuis(): ?\DateTimeImmutable
    {
        return $this->etatDepuis;
    }

    public function getVuLe(): ?\DateTimeImmutable
    {
        return $this->vuLe;
    }

    /** @return Collection<int, PortVoisin> */
    public function getVoisins(): Collection
    {
        return $this->voisins;
    }
}
