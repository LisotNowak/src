<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\BorneWifiRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Borne Wi-Fi d'un cluster Aruba Instant. Gardée quand elle disparaît du cluster (derniere_vue).
 */
#[ORM\Entity(repositoryClass: BorneWifiRepository::class)]
#[ORM\Table(name: 'borne_wifi', schema: 'gestion_switch')]
#[ORM\UniqueConstraint(name: 'borne_wifi_equipement_id_nom_key', columns: ['equipement_id', 'nom'])]
class BorneWifi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** cluster Instant */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    #[ORM\Column(length: 100)]
    private string $nom = '';

    #[ORM\Column(nullable: true, columnDefinition: 'INET')]
    private ?string $ip = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modele = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroSerie = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $mode = null;

    /** contrôleur virtuel du cluster */
    #[ORM\Column(options: ['default' => false])]
    private bool $maitre = false;

    #[ORM\Column(nullable: true)]
    private ?int $nbClients = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $canaux = null;

    /** UTC, d'après sa durée de fonctionnement */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $demarreeLe = null;

    /** interface montante (eth0…) */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $liaison = null;

    /** switch où la borne est branchée */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'switch_id', onDelete: 'SET NULL')]
    private ?Equipement $brancheSur = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $port = null;

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $premiereVue = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $derniereVue = null;

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

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function isMaitre(): bool
    {
        return $this->maitre;
    }

    public function getNbClients(): ?int
    {
        return $this->nbClients;
    }

    public function getCanaux(): ?string
    {
        return $this->canaux;
    }

    public function getDemarreeLe(): ?\DateTimeImmutable
    {
        return $this->demarreeLe;
    }

    public function getLiaison(): ?string
    {
        return $this->liaison;
    }

    public function getBrancheSur(): ?Equipement
    {
        return $this->brancheSur;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function getPremiereVue(): ?\DateTimeImmutable
    {
        return $this->premiereVue;
    }

    public function getDerniereVue(): ?\DateTimeImmutable
    {
        return $this->derniereVue;
    }
}
