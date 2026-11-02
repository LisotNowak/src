<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\ClientWifiRepository;
use Doctrine\ORM\Mapping as ORM;

/** Client Wi-Fi connecté, à la dernière collecte réussie de son cluster. */
#[ORM\Entity(repositoryClass: ClientWifiRepository::class)]
#[ORM\Table(name: 'client_wifi', schema: 'gestion_switch')]
class ClientWifi
{
    #[ORM\Id]
    #[ORM\Column(columnDefinition: 'MACADDR NOT NULL')]
    private string $mac = '';

    /** cluster Instant */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $borne = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(nullable: true, columnDefinition: 'INET')]
    private ?string $ip = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $systeme = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $essid = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $canal = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $bande = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $norme = null;

    /** dB */
    #[ORM\Column(nullable: true)]
    private ?int $signal = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $qualite = null;

    /** Mb/s */
    #[ORM\Column(nullable: true)]
    private ?int $debit = null;

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $vuLe = null;

    public function getMac(): string
    {
        return $this->mac;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function getBorne(): ?string
    {
        return $this->borne;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getSysteme(): ?string
    {
        return $this->systeme;
    }

    public function getEssid(): ?string
    {
        return $this->essid;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getCanal(): ?string
    {
        return $this->canal;
    }

    public function getBande(): ?string
    {
        return $this->bande;
    }

    public function getNorme(): ?string
    {
        return $this->norme;
    }

    public function getSignal(): ?int
    {
        return $this->signal;
    }

    public function getQualite(): ?string
    {
        return $this->qualite;
    }

    public function getDebit(): ?int
    {
        return $this->debit;
    }

    public function getVuLe(): ?\DateTimeImmutable
    {
        return $this->vuLe;
    }
}
