<?php

namespace App\Entity\dotation;

use App\Repository\dotation\StockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ORM\Table(name: "stock", schema: "dotation")]

class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\Column(length: 255)]
    private ?string $referenceArticle = null;

    #[ORM\Column(length: 255)]
    private ?string $nomTaille = null;

    #[ORM\Column(length: 255)]
    private ?string $nomCouleur = null;


    public function getNomTaille(): ?string
    {
        return $this->nomTaille;
    }

    public function setNomTaille(string $nomTaille): static
    {
        $this->nomTaille = $nomTaille;

        return $this;
    }

    public function getNomCouleur(): ?string
    {
        return $this->nomCouleur;
    }

    public function setNomCouleur(string $nomCouleur): static
    {
        $this->nomCouleur = $nomCouleur;

        return $this;
    }

    public function getReferenceArticle(): ?string
    {
        return $this->referenceArticle;
    }

    public function setReferenceArticle(string $referenceArticle): static
    {
        $this->referenceArticle = $referenceArticle;

        return $this;
    }

}
