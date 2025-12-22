<?php

namespace App\Entity\dotation;

use App\Repository\dotation\AssociationCouleursArticleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociationCouleursArticleRepository::class)]
#[ORM\Table(name: "association_couleurs_article", schema: "dotation")]
class AssociationCouleursArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'associationCouleurs')]
    #[ORM\JoinColumn(name: 'id_article', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Couleur::class)]
    #[ORM\JoinColumn(name: 'nom_couleur', referencedColumnName: 'nom', nullable: false, onDelete: 'CASCADE')]
    private ?Couleur $couleur = null;

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

    public function getCouleur(): ?Couleur
    {
        return $this->couleur;
    }

    public function setCouleur(?Couleur $couleur): static
    {
        $this->couleur = $couleur;

        return $this;
    }
}
