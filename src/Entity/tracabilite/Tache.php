<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\TacheRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TacheRepository::class)]
#[ORM\Table(name: 'vigne_tache')]
class Tache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $nom = null;

    /** Tâche RH/administrative : ne nécessite pas de parcelle */
    #[ORM\Column(options: ['default' => false])]
    private bool $sansParcel = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function isSansParcel(): bool
    {
        return $this->sansParcel;
    }

    public function setSansParcel(bool $sansParcel): static
    {
        $this->sansParcel = $sansParcel;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
