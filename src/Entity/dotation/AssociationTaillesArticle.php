<?php

namespace App\Entity\dotation;

use App\Repository\dotation\AssociationTaillesArticleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociationTaillesArticleRepository::class)]
#[ORM\Table(name: "association_tailles_article", schema: "dotation")]

class AssociationTaillesArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $idArticle = null;

    #[ORM\Column(length: 255)]
    private ?string $nomTaille = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
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

}
