<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\AppareilPresenceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Période pendant laquelle un appareil a été vu à la même position, à chaque collecte sans interruption.
 * debut et fin sont des collecte.debut : la vue v_appareil_vu redonne la position à chaque collecte.
 * Effacée au-delà de retention_presence_days (config/aruba/config.json ; 0 par défaut : jamais).
 * equipement_id n'a pas de clé étrangère, comme dans AppareilMouvement.
 */
#[ORM\Entity(repositoryClass: AppareilPresenceRepository::class)]
#[ORM\Table(name: 'appareil_presence', schema: 'gestion_switch')]
#[ORM\UniqueConstraint(name: 'appareil_presence_fin_mac_key', columns: ['fin', 'mac'])]
#[ORM\Index(columns: ['mac', 'debut'], name: 'appareil_presence_mac_idx')]
#[ORM\Index(columns: ['equipement_id', 'port'], name: 'appareil_presence_port_idx')]
class AppareilPresence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'mac', referencedColumnName: 'mac', nullable: false, onDelete: 'CASCADE')]
    private ?Appareil $appareil = null;

    /** UTC : première collecte de la période */
    #[ORM\Column]
    private ?\DateTimeImmutable $debut = null;

    /** UTC : dernière collecte de la période */
    #[ORM\Column]
    private ?\DateTimeImmutable $fin = null;

    #[ORM\Column(nullable: true)]
    private ?int $equipementId = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $port = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $vlan = null;

    #[ORM\Column(nullable: true, columnDefinition: 'INET')]
    private ?string $ip = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $borne = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $essid = null;

    /** dB (clients Wi-Fi), le plus faible de la période */
    #[ORM\Column(nullable: true)]
    private ?int $signalMin = null;

    /** dB (clients Wi-Fi), le plus fort de la période */
    #[ORM\Column(nullable: true)]
    private ?int $signalMax = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAppareil(): ?Appareil
    {
        return $this->appareil;
    }

    public function getDebut(): ?\DateTimeImmutable
    {
        return $this->debut;
    }

    public function getFin(): ?\DateTimeImmutable
    {
        return $this->fin;
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

    public function getSignalMin(): ?int
    {
        return $this->signalMin;
    }

    public function getSignalMax(): ?int
    {
        return $this->signalMax;
    }
}
