<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\PortVoisinRepository;
use Doctrine\ORM\Mapping as ORM;

/** Voisin LLDP d'un port (borne, switch, téléphone, PC…), à la dernière collecte réussie du switch. */
#[ORM\Entity(repositoryClass: PortVoisinRepository::class)]
#[ORM\Table(name: 'port_voisin', schema: 'gestion_switch')]
#[ORM\Index(columns: ['port_id'], name: 'port_voisin_port_idx')]
#[ORM\Index(columns: ['chassis_id'], name: 'port_voisin_chassis_idx')]
class PortVoisin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'voisins')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Port $port = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $chassisId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $portDistant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descriptionPort = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $ipGestion = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $capacites = null;

    /** switch géré reconnu (par sa MAC, son nom ou son adresse) */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Equipement $equipementVoisin = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPort(): ?Port
    {
        return $this->port;
    }

    public function getChassisId(): ?string
    {
        return $this->chassisId;
    }

    public function getPortDistant(): ?string
    {
        return $this->portDistant;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDescriptionPort(): ?string
    {
        return $this->descriptionPort;
    }

    public function getIpGestion(): ?string
    {
        return $this->ipGestion;
    }

    public function getCapacites(): ?string
    {
        return $this->capacites;
    }

    public function getEquipementVoisin(): ?Equipement
    {
        return $this->equipementVoisin;
    }
}
