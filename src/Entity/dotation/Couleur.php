<?php

namespace App\Entity\dotation;

use App\Repository\dotation\CouleurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CouleurRepository::class)]
#[ORM\Table(name: "couleur", schema: "dotation")]
class Couleur
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $codeCouleur = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCodeCouleur(): ?string
    {
        return $this->codeCouleur;
    }

    public function setCodeCouleur(string $codeCouleur): static
    {
        $this->codeCouleur = $codeCouleur;

        return $this;
    }
}
