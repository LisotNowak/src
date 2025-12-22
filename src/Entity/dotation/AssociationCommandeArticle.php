<?php

namespace App\Entity\dotation;

use App\Repository\dotation\AssociationCommandeArticleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociationCommandeArticleRepository::class)]
#[ORM\Table(name: "association_commande_article", schema: "dotation")]
class AssociationCommandeArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'id_article', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Taille::class)]
    #[ORM\JoinColumn(name: 'nom_taille', referencedColumnName: 'nom', nullable: true, onDelete: 'CASCADE')]
    private ?Taille $taille = null;

    #[ORM\ManyToOne(targetEntity: Couleur::class)]
    #[ORM\JoinColumn(name: 'nom_couleur', referencedColumnName: 'nom', nullable: true, onDelete: 'CASCADE')]
    private ?Couleur $couleur = null;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(name: 'id_commande', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\Column(nullable: true)]
    private ?int $nb = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function getTaille(): ?Taille
    {
        return $this->taille;
    }

    public function setTaille(?Taille $taille): static
    {
        $this->taille = $taille;

        return $this;
    }

    public function getCouleur(): ?Couleur
    {
        return $this->couleur;
    }

    public function setCouleur(?Couleur $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;

        return $this;
    }

    public function getNb(): ?int
    {
        return $this->nb;
    }

    public function setNb(?int $nb): static
    {
        $this->nb = $nb;

        return $this;
    }
}
