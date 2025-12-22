<?php

namespace App\Entity\dotation;

use App\Repository\dotation\TailleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TailleRepository::class)]
#[ORM\Table(name: "taille", schema: "dotation")]
class Taille
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }
}
