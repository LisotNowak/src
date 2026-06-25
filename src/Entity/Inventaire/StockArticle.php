<?php

namespace App\Entity\Inventaire;

use App\Repository\Inventaire\StockArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockArticleRepository::class)]
#[ORM\Table(name: 'stock_article', schema: 'inventaire')]
#[ORM\UniqueConstraint(name: 'UNIQ_CODE_LOT_EMPL', fields: ['codeArticle', 'numeroLot', 'emplacement'])]
class StockArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $depot = null;

    #[ORM\Column(length: 50)]
    private ?string $codeArticle = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $numeroLot = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $emplacement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $stockDisponible = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $quantiteAffectee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $stockAffecte = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $uniteMesure = null;

    #[ORM\Column(nullable: true)]
    private ?bool $affecte = null;

    #[ORM\Column(nullable: true)]
    private ?int $statut = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $attributPrincipal1 = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $attributPrincipal2 = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $comptage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepot(): ?string
    {
        return $this->depot;
    }

    public function setDepot(string $depot): static
    {
        $this->depot = $depot;
        return $this;
    }

    public function getCodeArticle(): ?string
    {
        return $this->codeArticle;
    }

    public function setCodeArticle(string $codeArticle): static
    {
        $this->codeArticle = $codeArticle;
        return $this;
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

    public function getNumeroLot(): ?string
    {
        return $this->numeroLot;
    }

    public function setNumeroLot(?string $numeroLot): static
    {
        $this->numeroLot = $numeroLot;
        return $this;
    }

    public function getEmplacement(): ?string
    {
        return $this->emplacement;
    }

    public function setEmplacement(?string $emplacement): static
    {
        $this->emplacement = $emplacement;
        return $this;
    }

    public function getStockDisponible(): ?string
    {
        return $this->stockDisponible;
    }

    public function setStockDisponible(?string $stockDisponible): static
    {
        $this->stockDisponible = $stockDisponible;
        return $this;
    }

    public function getQuantiteAffectee(): ?string
    {
        return $this->quantiteAffectee;
    }

    public function setQuantiteAffectee(?string $quantiteAffectee): static
    {
        $this->quantiteAffectee = $quantiteAffectee;
        return $this;
    }

    public function getStockAffecte(): ?string
    {
        return $this->stockAffecte;
    }

    public function setStockAffecte(?string $stockAffecte): static
    {
        $this->stockAffecte = $stockAffecte;
        return $this;
    }

    public function getUniteMesure(): ?string
    {
        return $this->uniteMesure;
    }

    public function setUniteMesure(?string $uniteMesure): static
    {
        $this->uniteMesure = $uniteMesure;
        return $this;
    }

    public function isAffecte(): ?bool
    {
        return $this->affecte;
    }

    public function setAffecte(?bool $affecte): static
    {
        $this->affecte = $affecte;
        return $this;
    }

    public function getStatut(): ?int
    {
        return $this->statut;
    }

    public function setStatut(?int $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getAttributPrincipal1(): ?string
    {
        return $this->attributPrincipal1;
    }

    public function setAttributPrincipal1(?string $attributPrincipal1): static
    {
        $this->attributPrincipal1 = $attributPrincipal1;
        return $this;
    }

    public function getAttributPrincipal2(): ?string
    {
        return $this->attributPrincipal2;
    }

    public function setAttributPrincipal2(?string $attributPrincipal2): static
    {
        $this->attributPrincipal2 = $attributPrincipal2;
        return $this;
    }

    public function getComptage(): ?string
    {
        return $this->comptage;
    }

    public function setComptage(?string $comptage): static
    {
        $this->comptage = $comptage;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }
}
