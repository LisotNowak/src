<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\CartePersoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Carte personnalisée imprimable : sélection de parcelles sur fond blanc,
 * chacune pouvant être déplacée pour rapprocher des parcelles éloignées.
 */
#[ORM\Entity(repositoryClass: CartePersoRepository::class)]
#[ORM\Table(name: 'vigne_carte_perso')]
class CartePerso
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    /**
     * Parcelles affichées et leur décalage en mètres (Lambert 93) par rapport à leur position réelle :
     * { "slug": [dx, dy], ... }
     */
    #[ORM\Column(type: Types::JSON)]
    private array $disposition = [];

    /** Options d'affichage et d'impression (noms, couleurs, format…) */
    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $auteur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $creeA = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $modifieA = null;

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

    public function getDisposition(): array
    {
        return $this->disposition;
    }

    public function setDisposition(array $disposition): static
    {
        $this->disposition = $disposition;
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getAuteur(): ?string
    {
        return $this->auteur;
    }

    public function setAuteur(?string $auteur): static
    {
        $this->auteur = $auteur;
        return $this;
    }

    public function getCreeA(): ?\DateTimeImmutable
    {
        return $this->creeA;
    }

    public function setCreeA(\DateTimeImmutable $creeA): static
    {
        $this->creeA = $creeA;
        return $this;
    }

    public function getModifieA(): ?\DateTimeImmutable
    {
        return $this->modifieA;
    }

    public function setModifieA(?\DateTimeImmutable $modifieA): static
    {
        $this->modifieA = $modifieA;
        return $this;
    }
}
