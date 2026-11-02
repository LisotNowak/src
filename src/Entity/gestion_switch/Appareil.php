<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\AppareilRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Appareil vu sur le réseau (ancien history.json) : dernière position connue, par adresse MAC.
 * Écrit par App\Service\Aruba\Store à chaque collecte.
 */
#[ORM\Entity(repositoryClass: AppareilRepository::class)]
#[ORM\Table(name: 'appareil', schema: 'gestion_switch')]
#[ORM\Index(columns: ['derniere_vue'], name: 'appareil_derniere_vue_idx')]
#[ORM\Index(columns: ['ip'], name: 'appareil_ip_idx')]
class Appareil
{
    #[ORM\Id]
    #[ORM\Column(columnDefinition: 'MACADDR NOT NULL')]
    private string $mac = '';

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $premiereVue = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $derniereVue = null;

    #[ORM\Column(nullable: true, columnDefinition: 'INET')]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $constructeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $glpiNom = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Equipement $equipement = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $port = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $vlan = null;

    /** access | uplink | ap | ext | lag */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $role = null;

    /** borne Wi-Fi et réseau, si l'appareil a été vu en Wi-Fi */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $borne = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $essid = null;

    /** système d'exploitation annoncé en Wi-Fi (Windows, iOS, Android…) */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $systeme = null;

    public function getMac(): string
    {
        return $this->mac;
    }

    public function getPremiereVue(): ?\DateTimeImmutable
    {
        return $this->premiereVue;
    }

    public function getDerniereVue(): ?\DateTimeImmutable
    {
        return $this->derniereVue;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getConstructeur(): ?string
    {
        return $this->constructeur;
    }

    public function getGlpiNom(): ?string
    {
        return $this->glpiNom;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function getVlan(): ?string
    {
        return $this->vlan;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getBorne(): ?string
    {
        return $this->borne;
    }

    public function getEssid(): ?string
    {
        return $this->essid;
    }

    public function getSysteme(): ?string
    {
        return $this->systeme;
    }
}
