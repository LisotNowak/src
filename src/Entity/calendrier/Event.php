<?php

namespace App\Entity\calendrier;

use App\Repository\calendrier\EventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: "event", schema: "calendrier")]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    private ?string $du = null;

    #[ORM\Column(length: 255)]
    private ?string $au = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $auteur = null;

    #[ORM\Column(length: 255)]
    private ?string $categorie = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $lieu = null;

    #[ORM\Column(length: 255)]
    private ?string $langues = null;

    #[ORM\Column(length: 255)]
    private ?string $produits = null;

    #[ORM\Column(length: 255)]
    private ?string $invites = null;

    #[ORM\Column(length: 255)]
    private ?string $operateurs = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }


    public function getDu(): ?string
    {
        return $this->du;
    }

    public function setDu(string $du): static
    {
        $this->du = $du;

        return $this;
    }

    public function getAu(): ?string
    {
        return $this->au;
    }

    public function setAu(string $au): static
    {
        $this->au = $au;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAuteur(): ?string
    {
        return $this->auteur;
    }

    public function setAuteur(string $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getLangues(): ?string
    {
        return $this->langues;
    }

    public function setLangues(string $langues): static
    {
        $this->langues = $langues;

        return $this;
    }

    public function getProduits(): ?string
    {
        return $this->produits;
    }

    public function setProduits(string $produits): static
    {
        $this->produits = $produits;

        return $this;
    }

    public function getInvites(): ?string
    {
        return $this->invites;
    }

    public function setInvites(string $invites): static
    {
        $this->invites = $invites;

        return $this;
    }

    public function getOperateurs(): ?string
    {
        return $this->operateurs;
    }

    public function setOperateurs(string $operateurs): static
    {
        $this->operateurs = $operateurs;

        return $this;
    }
}
