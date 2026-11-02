<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\CollecteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une collecte (bouton « Actualiser »). Le snapshot (1 à 2 Mo) est lu directement par
 * App\Service\Aruba\Store : éviter de charger des Collecte en nombre par l'ORM.
 * Au-delà de retention_full_days (config.json, 7 jours par défaut), il ne reste qu'une photo par jour ; il est vidé
 * au-delà de retention_days (0 par défaut : jamais). La ligne et les résultats restent.
 */
#[ORM\Entity(repositoryClass: CollecteRepository::class)]
#[ORM\Table(name: 'collecte', schema: 'gestion_switch')]
#[ORM\Index(columns: ['debut'], name: 'collecte_debut_idx')]
class Collecte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $debut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fin = null;

    /** ok | partiel (certains équipements en erreur) | erreur */
    #[ORM\Column(length: 20)]
    private string $statut = 'ok';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $lancePar = null;

    #[ORM\Column(type: 'json', nullable: true, options: ['jsonb' => true])]
    private ?array $snapshot = null;

    // ── résumé (évolution du réseau sans relire la photo) ──

    /** secondes */
    #[ORM\Column(nullable: true)]
    private ?int $duree = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbEquipements = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbEquipementsOk = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbMacUniques = null;

    #[ORM\Column(nullable: true)]
    private ?int $portsActifs = null;

    #[ORM\Column(nullable: true)]
    private ?int $portsTotal = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbBornes = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbClientsWifi = null;

    /** appareils du réseau retrouvés dans GLPI */
    #[ORM\Column(nullable: true)]
    private ?int $nbGlpi = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDebut(): ?\DateTimeImmutable
    {
        return $this->debut;
    }

    public function getFin(): ?\DateTimeImmutable
    {
        return $this->fin;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function getLancePar(): ?string
    {
        return $this->lancePar;
    }

    public function getSnapshot(): ?array
    {
        return $this->snapshot;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function getNbEquipements(): ?int
    {
        return $this->nbEquipements;
    }

    public function getNbEquipementsOk(): ?int
    {
        return $this->nbEquipementsOk;
    }

    public function getNbMacUniques(): ?int
    {
        return $this->nbMacUniques;
    }

    public function getPortsActifs(): ?int
    {
        return $this->portsActifs;
    }

    public function getPortsTotal(): ?int
    {
        return $this->portsTotal;
    }

    public function getNbBornes(): ?int
    {
        return $this->nbBornes;
    }

    public function getNbClientsWifi(): ?int
    {
        return $this->nbClientsWifi;
    }

    public function getNbGlpi(): ?int
    {
        return $this->nbGlpi;
    }
}
