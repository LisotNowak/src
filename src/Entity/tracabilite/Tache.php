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

    #[ORM\ManyToOne(targetEntity: TypeTache::class, inversedBy: 'taches')]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TypeTache $type = null;

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

    public function getType(): ?TypeTache
    {
        return $this->type;
    }

    public function setType(?TypeTache $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Noms des équipes qui peuvent saisir cette tâche ; vide = toutes les équipes
     * (tâche sans type, ou type sans équipe).
     * @return string[]
     */
    public function getEquipesAutorisees(): array
    {
        return $this->type ? $this->type->getEquipes()->map(fn(Equipe $e) => $e->getNom())->getValues() : [];
    }

    public function estDisponiblePour(?string $equipeNom): bool
    {
        $equipes = $this->getEquipesAutorisees();
        return !$equipes || in_array($equipeNom, $equipes, true);
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
