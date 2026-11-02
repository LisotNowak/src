<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\AppareilMouvementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Nouvelle position d'un appareil (switch, port ou VLAN différent de la collecte précédente).
 * equipement_id n'a pas de clé étrangère : l'historique reste si le switch est supprimé.
 */
#[ORM\Entity(repositoryClass: AppareilMouvementRepository::class)]
#[ORM\Table(name: 'appareil_mouvement', schema: 'gestion_switch')]
#[ORM\Index(columns: ['mac', 'vu_le'], name: 'appareil_mouvement_mac_idx')]
#[ORM\Index(columns: ['vu_le'], name: 'appareil_mouvement_vu_le_idx')]
#[ORM\Index(columns: ['equipement_id', 'port'], name: 'appareil_mouvement_port_idx')]
class AppareilMouvement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'mac', referencedColumnName: 'mac', onDelete: 'CASCADE')]
    private ?Appareil $appareil = null;

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $vuLe = null;

    #[ORM\Column(nullable: true)]
    private ?int $equipementId = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $port = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $vlan = null;

    #[ORM\Column(nullable: true, columnDefinition: 'INET')]
    private ?string $ip = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $borne = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $essid = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $role = null;

    /** arrivee (1re fois vu) | filaire | wifi (changement de borne) */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $nature = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAppareil(): ?Appareil
    {
        return $this->appareil;
    }

    public function getVuLe(): ?\DateTimeImmutable
    {
        return $this->vuLe;
    }

    public function getEquipementId(): ?int
    {
        return $this->equipementId;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function getVlan(): ?string
    {
        return $this->vlan;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getBorne(): ?string
    {
        return $this->borne;
    }

    public function getEssid(): ?string
    {
        return $this->essid;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }
}
