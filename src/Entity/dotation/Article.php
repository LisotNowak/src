<?php

namespace App\Entity\dotation;

use App\Repository\dotation\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: "article", schema: "dotation")]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?int $prix = null;

    #[ORM\Column(length: 255)]
    private ?string $reference = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $nomType = null;

    #[ORM\Column]
    private ?int $point = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: AssociationTaillesArticle::class, cascade: ['persist','remove'])]
    private Collection $associationTailles;

    #[ORM\OneToMany(mappedBy: 'article', targetEntity: AssociationCouleursArticle::class, cascade: ['persist','remove'])]
    private Collection $associationCouleurs;

    public function __construct()
    {
        $this->associationTailles = new ArrayCollection();
        $this->associationCouleurs = new ArrayCollection();
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

    public function getPrix(): ?int
    {
        return $this->prix;
    }

    public function setPrix(int $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

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

    public function getNomType(): ?string
    {
        return $this->nomType;
    }

    public function setNomType(string $nomType): static
    {
        $this->nomType = $nomType;

        return $this;
    }

    public function getPoint(): ?int
    {
        return $this->point;
    }

    public function setPoint(int $point): static
    {
        $this->point = $point;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection|AssociationTaillesArticle[]
     */
    public function getAssociationTailles(): Collection
    {
        return $this->associationTailles;
    }

    public function addAssociationTaille(AssociationTaillesArticle $associationTaillesArticle): static
    {
        if (!$this->associationTailles->contains($associationTaillesArticle)) {
            $this->associationTailles->add($associationTaillesArticle);
            $associationTaillesArticle->setArticle($this);
        }

        return $this;
    }

    public function removeAssociationTaille(AssociationTaillesArticle $associationTaillesArticle): static
    {
        if ($this->associationTailles->removeElement($associationTaillesArticle)) {
            if ($associationTaillesArticle->getArticle() === $this) {
                $associationTaillesArticle->setArticle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|AssociationCouleursArticle[]
     */
    public function getAssociationCouleurs(): Collection
    {
        return $this->associationCouleurs;
    }

    public function addAssociationCouleur(AssociationCouleursArticle $associationCouleursArticle): static
    {
        if (!$this->associationCouleurs->contains($associationCouleursArticle)) {
            $this->associationCouleurs->add($associationCouleursArticle);
            $associationCouleursArticle->setArticle($this);
        }

        return $this;
    }

    public function removeAssociationCouleur(AssociationCouleursArticle $associationCouleursArticle): static
    {
        if ($this->associationCouleurs->removeElement($associationCouleursArticle)) {
            if ($associationCouleursArticle->getArticle() === $this) {
                $associationCouleursArticle->setArticle(null);
            }
        }

        return $this;
    }
}
