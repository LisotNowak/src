<?php

namespace App\Entity\dotation;

use App\Repository\dotation\AssociationCommandeArticleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociationCommandeArticleRepository::class)]
#[ORM\Table(name: "association_commande_article", schema: "dotation")]
class AssociationCommandeArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $idArticle = null;

    #[ORM\Column(length: 255)]
    private ?string $nomCouleur = null;

    #[ORM\Column(length: 255)]
    private ?string $nomTaille = null;

    #[ORM\Column]
    private ?int $idCommande = null;

    #[ORM\Column]
    private ?int $nb = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdArticle(): ?int
    {
        return $this->idArticle;
    }

    public function setIdArticle(int $idArticle): static
    {
        $this->idArticle = $idArticle;

        return $this;
    }

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

    public function getNb(): ?int
    {
        return $this->nb;
    }

    public function setNb(int $nb): static
    {
        $this->nb = $nb;

        return $this;
    }

    public function getIdCommande(): ?int
    {
        return $this->idCommande;
    }

    public function setIdCommande(int $idCommande): static
    {
        $this->idCommande = $idCommande;

        return $this;
    }

}
