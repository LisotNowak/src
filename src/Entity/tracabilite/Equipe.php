<?php

namespace App\Entity\tracabilite;

use App\Entity\User;
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

    /** Utilisateurs (managers tracabilité) qui voient les données de cette équipe */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'vigne_equipe_user')]
    #[ORM\JoinColumn(name: 'equipe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['email' => 'ASC'])]
    private Collection $responsables;

    public function __construct()
    {
        $this->ouvriers = new ArrayCollection();
        $this->responsables = new ArrayCollection();
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

    /** @return Collection<int, User> */
    public function getResponsables(): Collection
    {
        return $this->responsables;
    }

    public function addResponsable(User $user): static
    {
        if (!$this->responsables->contains($user)) {
            $this->responsables->add($user);
        }
        return $this;
    }

    public function removeResponsable(User $user): static
    {
        $this->responsables->removeElement($user);
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
