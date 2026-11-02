<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\CollecteEquipementRepository;
use Doctrine\ORM\Mapping as ORM;

/** Résultat d'une collecte pour un équipement. */
#[ORM\Entity(repositoryClass: CollecteEquipementRepository::class)]
#[ORM\Table(name: 'collecte_equipement', schema: 'gestion_switch')]
class CollecteEquipement
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Collecte $collecte = null;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    /** ok | erreur */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $statut = null;

    /** secondes */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 1, nullable: true)]
    private ?string $duree = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $erreur = null;

    public function getCollecte(): ?Collecte
    {
        return $this->collecte;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function getDuree(): ?string
    {
        return $this->duree;
    }

    public function getErreur(): ?string
    {
        return $this->erreur;
    }
}
