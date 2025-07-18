<?php

namespace App\Entity\client;

use App\Repository\client\CategorieRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: "catégorie", schema: "client")]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "nom_catégorie", type: "string", length: 128, unique: true)]
    private ?string $nomCategorie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomCategorie(): ?string
    {
        return $this->nomCategorie;
    }

    public function setNomCategorie(?string $nomCategorie): self
    {
        $this->nomCategorie = $nomCategorie;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nomCategorie ?? '';
    }
}
