<?php

namespace App\Entity\dotation;

use App\Repository\dotation\AssociationTaillesArticleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociationTaillesArticleRepository::class)]
#[ORM\Table(name: "association_tailles_article", schema: "dotation")]
class AssociationTaillesArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'associationTailles')]
    #[ORM\JoinColumn(name: 'id_article', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\ManyToOne(targetEntity: Taille::class)]
    #[ORM\JoinColumn(name: 'nom_taille', referencedColumnName: 'nom', nullable: false, onDelete: 'CASCADE')]
    private ?Taille $taille = null;

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
}
