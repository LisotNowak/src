<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\VlanRepository;
use Doctrine\ORM\Mapping as ORM;

/** VLAN déclaré sur un switch (remplacé à chaque collecte réussie du switch, par App\Service\Aruba\Store). */
#[ORM\Entity(repositoryClass: VlanRepository::class)]
#[ORM\Table(name: 'vlan', schema: 'gestion_switch')]
class Vlan
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    #[ORM\Id]
    #[ORM\Column(length: 10)]
    private string $vlan = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom = null;

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function getVlan(): string
    {
        return $this->vlan;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }
}
