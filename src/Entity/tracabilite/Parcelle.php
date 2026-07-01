<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\ParcelleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParcelleRepository::class)]
#[ORM\Table(name: 'vigne_parcelle')]
class Parcelle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant technique (ex: artigues_centre_1) */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    /** Nombre total de pieds plantés */
    #[ORM\Column(nullable: true)]
    private ?int $nombrePieds = null;

    /** Surface en hectares */
    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $surface = null;

    /** Cépage principal (CS, M, PV, etc.) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $cepage = null;

    /** Gamme (FL, GV, PA, etc.) */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $gamme = null;

    /**
     * Géométrie GeoJSON (MultiPolygon) stockée en JSONB PostgreSQL.
     * Coordonnées en Lambert 93 / RGF93 (EPSG:2154), unité : mètres.
     * Pour les parcelles avec plusieurs polygones, tableau de MultiPolygon.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $geometrie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getNombrePieds(): ?int
    {
        return $this->nombrePieds;
    }

    public function setNombrePieds(?int $nombrePieds): static
    {
        $this->nombrePieds = $nombrePieds;
        return $this;
    }

    public function getSurface(): ?string
    {
        return $this->surface;
    }

    public function setSurface(?string $surface): static
    {
        $this->surface = $surface;
        return $this;
    }

    public function getCepage(): ?string
    {
        return $this->cepage;
    }

    public function setCepage(?string $cepage): static
    {
        $this->cepage = $cepage;
        return $this;
    }

    public function getGamme(): ?string
    {
        return $this->gamme;
    }

    public function setGamme(?string $gamme): static
    {
        $this->gamme = $gamme;
        return $this;
    }

    public function getGeometrie(): ?array
    {
        return $this->geometrie;
    }

    public function setGeometrie(?array $geometrie): static
    {
        $this->geometrie = $geometrie;
        return $this;
    }

    public function __toString(): string
    {
        return $this->slug ?? '';
    }
}
