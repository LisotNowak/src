<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\MacVueRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entrée de la table MAC d'un switch (toutes les observations, transit compris), à la dernière collecte
 * réussie du switch. principal : emplacement retenu pour l'appareil (voir Appareil).
 */
#[ORM\Entity(repositoryClass: MacVueRepository::class)]
#[ORM\Table(name: 'mac_vue', schema: 'gestion_switch')]
#[ORM\Index(columns: ['mac'], name: 'mac_vue_mac_idx')]
class MacVue
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    #[ORM\Id]
    #[ORM\Column(length: 30)]
    private string $port = '';

    #[ORM\Id]
    #[ORM\Column(columnDefinition: 'MACADDR NOT NULL')]
    private string $mac = '';

    #[ORM\Id]
    #[ORM\Column(length: 10, options: ['default' => ''])]
    private string $vlan = '';

    /** rôle du port (uplink : MAC vue en transit) */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $role = null;

    /** dynamic | static… */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $type = null;

    /** d'après les tables ARP */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ip = null;

    /** équipement intermédiaire (borne, switch non géré) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $via = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $principal = false;

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $vuLe = null;

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function getPort(): string
    {
        return $this->port;
    }

    public function getMac(): string
    {
        return $this->mac;
    }

    public function getVlan(): string
    {
        return $this->vlan;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getVia(): ?string
    {
        return $this->via;
    }

    public function isPrincipal(): bool
    {
        return $this->principal;
    }

    public function getVuLe(): ?\DateTimeImmutable
    {
        return $this->vuLe;
    }
}
