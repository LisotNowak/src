<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\PortEvenementRepository;
use Doctrine\ORM\Mapping as ORM;

/** Changement d'un port d'une collecte à l'autre (gardé sans limite de durée). */
#[ORM\Entity(repositoryClass: PortEvenementRepository::class)]
#[ORM\Table(name: 'port_evenement', schema: 'gestion_switch')]
#[ORM\Index(columns: ['equipement_id', 'port', 'vu_le'], name: 'port_evenement_port_idx')]
#[ORM\Index(columns: ['vu_le'], name: 'port_evenement_vu_le_idx')]
class PortEvenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    /** nom du port (pas de clé étrangère : l'événement reste si le port disparaît) */
    #[ORM\Column(length: 30)]
    private string $port = '';

    /** UTC */
    #[ORM\Column]
    private ?\DateTimeImmutable $vuLe = null;

    /** lien | admin | vitesse | description | vlan | voisin | apparu | retire */
    #[ORM\Column(length: 20)]
    private string $champ = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $avant = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $apres = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function getPort(): string
    {
        return $this->port;
    }

    public function getVuLe(): ?\DateTimeImmutable
    {
        return $this->vuLe;
    }

    public function getChamp(): string
    {
        return $this->champ;
    }

    public function getAvant(): ?string
    {
        return $this->avant;
    }

    public function getApres(): ?string
    {
        return $this->apres;
    }
}
