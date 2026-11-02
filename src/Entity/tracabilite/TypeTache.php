<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\TypeTacheRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeTacheRepository::class)]
#[ORM\Table(name: 'vigne_type_tache')]
class TypeTache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $nom = null;

    /** Équipes qui réalisent ce type de tâche ; aucune équipe = disponible pour toutes */
    #[ORM\ManyToMany(targetEntity: Equipe::class)]
    #[ORM\JoinTable(name: 'vigne_type_tache_equipe')]
    #[ORM\JoinColumn(name: 'type_tache_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'equipe_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $equipes;

    #[ORM\OneToMany(mappedBy: 'type', targetEntity: Tache::class)]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $taches;

    public function __construct()
    {
        $this->equipes = new ArrayCollection();
        $this->taches = new ArrayCollection();
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

    /** @return Collection<int, Equipe> */
    public function getEquipes(): Collection
    {
        return $this->equipes;
    }

    public function addEquipe(Equipe $equipe): static
    {
        if (!$this->equipes->contains($equipe)) {
            $this->equipes->add($equipe);
        }
        return $this;
    }

    public function removeEquipe(Equipe $equipe): static
    {
        $this->equipes->removeElement($equipe);
        return $this;
    }

    /** @return Collection<int, Tache> */
    public function getTaches(): Collection
    {
        return $this->taches;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
