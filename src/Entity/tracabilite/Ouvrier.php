<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\OuvrierRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OuvrierRepository::class)]
#[ORM\Table(name: 'vigne_ouvrier')]
class Ouvrier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomComplet = null;

    #[ORM\ManyToOne(inversedBy: 'ouvriers')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Equipe $equipe = null;

    /** Permanent, CDD ou Saisonnier */
    #[ORM\Column(length: 50, options: ['default' => 'Permanent'])]
    private string $contrat = 'Permanent';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomComplet(): ?string
    {
        return $this->nomComplet;
    }

    public function setNomComplet(string $nomComplet): static
    {
        $this->nomComplet = $nomComplet;
        return $this;
    }

    public function getEquipe(): ?Equipe
    {
        return $this->equipe;
    }

    public function setEquipe(?Equipe $equipe): static
    {
        $this->equipe = $equipe;
        return $this;
    }

    public function getContrat(): string
    {
        return $this->contrat;
    }

    public function setContrat(string $contrat): static
    {
        $this->contrat = $contrat;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nomComplet ?? '';
    }
}
