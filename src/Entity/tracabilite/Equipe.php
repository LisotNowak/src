<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\EquipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EquipeRepository::class)]
#[ORM\Table(name: 'vigne_equipe')]
class Equipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $nom = null;

    #[ORM\OneToMany(mappedBy: 'equipe', targetEntity: Ouvrier::class)]
    private Collection $ouvriers;

    public function __construct()
    {
        $this->ouvriers = new ArrayCollection();
    }

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

    /** @return Collection<int, Ouvrier> */
    public function getOuvriers(): Collection
    {
        return $this->ouvriers;
    }

    public function addOuvrier(Ouvrier $ouvrier): static
    {
        if (!$this->ouvriers->contains($ouvrier)) {
            $this->ouvriers->add($ouvrier);
            $ouvrier->setEquipe($this);
        }
        return $this;
    }

    public function removeOuvrier(Ouvrier $ouvrier): static
    {
        if ($this->ouvriers->removeElement($ouvrier)) {
            if ($ouvrier->getEquipe() === $this) {
                $ouvrier->setEquipe(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
