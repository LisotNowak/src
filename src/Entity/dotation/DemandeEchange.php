<?php

namespace App\Entity\dotation;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\dotation\DemandeEchangeRepository;

#[ORM\Entity(repositoryClass: DemandeEchangeRepository::class)]
#[ORM\Table(name: "demande_echange", schema: "dotation")]
class DemandeEchange
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToOne(targetEntity: AssociationCommandeArticle::class)]
    #[ORM\JoinColumn(name: "old_assoc_id", referencedColumnName: "id", nullable: false)]
    private ?AssociationCommandeArticle $oldAssociationCommandeArticle = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: "new_article_id", referencedColumnName: "id", nullable: false)]
    private ?Article $newArticle = null;

    #[ORM\Column(length: 255)]
    private ?string $newTaille = null;

    #[ORM\Column(length: 255)]
    private ?string $newCouleur = null;

    #[ORM\Column(type: "text")]
    private ?string $reason = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'En attente'; // Valeur par défaut

    #[ORM\Column(nullable: true)]
    private ?int $quantite = 1;

    #[ORM\Column(nullable: true)]
    private ?int $quantiteNew = 1;

    #[ORM\Column(nullable: true)]
    private ?int $pointsDeduits = 0;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $dateDemande = null;

    public function __construct()
    {
        $this->dateDemande = new \DateTime();
    }

    // --- Getters and Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getOldAssociationCommandeArticle(): ?AssociationCommandeArticle
    {
        return $this->oldAssociationCommandeArticle;
    }

    public function setOldAssociationCommandeArticle(?AssociationCommandeArticle $oldAssociationCommandeArticle): static
    {
        $this->oldAssociationCommandeArticle = $oldAssociationCommandeArticle;
        return $this;
    }

    public function getNewArticle(): ?Article
    {
        return $this->newArticle;
    }

    public function setNewArticle(?Article $newArticle): static
    {
        $this->newArticle = $newArticle;
        return $this;
    }

    public function getNewTaille(): ?string
    {
        return $this->newTaille;
    }

    public function setNewTaille(string $newTaille): static
    {
        $this->newTaille = $newTaille;
        return $this;
    }

    public function getNewCouleur(): ?string
    {
        return $this->newCouleur;
    }

    public function setNewCouleur(string $newCouleur): static
    {
        $this->newCouleur = $newCouleur;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPointsDeduits(): ?int
    {
        return $this->pointsDeduits;
    }

    public function setPointsDeduits(?int $pointsDeduits): static
    {
        $this->pointsDeduits = $pointsDeduits;
        return $this;
    }

    public function getQuantiteNew(): ?int
    {
        return $this->quantiteNew;
    }

    public function setQuantiteNew(?int $quantiteNew): static
    {
        $this->quantiteNew = $quantiteNew;
        return $this;
    }

    public function getDateDemande(): ?\DateTimeInterface
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeInterface $dateDemande): static
    {
        $this->dateDemande = $dateDemande;
        return $this;
    }
}