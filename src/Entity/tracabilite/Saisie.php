<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\SaisieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Enregistrement terrain ou RH.
 * Les noms (chef, personnel, tâche, parcelle) sont dénormalisés pour
 * préserver l'historique même en cas de modification des référentiels.
 */
#[ORM\Entity(repositoryClass: SaisieRepository::class)]
#[ORM\Table(name: 'vigne_saisie')]
#[ORM\Index(fields: ['mois'], name: 'idx_saisie_mois')]
#[ORM\Index(fields: ['chefNom'], name: 'idx_saisie_chef')]
#[ORM\Index(fields: ['personnelNom'], name: 'idx_saisie_personnel')]
#[ORM\Index(fields: ['parcelleNom'], name: 'idx_saisie_parcelle')]
class Saisie
{
    /** UUID généré côté applicatif (compatible avec l'export JSON du HTML) */
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private ?string $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateTravail = null;

    /** Format YYYY-MM, permet des filtres mensuels rapides */
    #[ORM\Column(length: 7)]
    private ?string $mois = null;

    /** Nom de l'équipe / chef de secteur */
    #[ORM\Column(length: 100)]
    private ?string $chefNom = null;

    /** Nom complet de la personne ou "Saisonniers non nominatifs" */
    #[ORM\Column(length: 255)]
    private ?string $personnelNom = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $personnelContrat = null;

    #[ORM\Column(length: 255)]
    private ?string $tacheNom = null;

    /** Null pour les tâches RH/administratives */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parcelleNom = null;

    /** Heures brutes saisies */
    #[ORM\Column(options: ['default' => 0])]
    private float $heures = 0.0;

    /** Heures nettes après déduction de la pause (pour calcul du rendement) */
    #[ORM\Column(options: ['default' => 0])]
    private float $heuresNettes = 0.0;

    /** Nombre de personnes représentées par cette ligne (>1 pour les saisonniers non nominatifs) */
    #[ORM\Column(options: ['default' => 1])]
    private int $effectif = 1;

    /** auto | hiver | ete | aucune */
    #[ORM\Column(length: 20, options: ['default' => 'auto'])]
    private string $modePause = 'auto';

    #[ORM\Column(options: ['default' => 0])]
    private int $minutesPause = 0;

    /** Avancement en pourcentage (0-100) */
    #[ORM\Column(options: ['default' => 0])]
    private float $avancement = 0.0;

    /** Pieds réalisés par cette personne/ce groupe */
    #[ORM\Column(options: ['default' => 0])]
    private float $pieds = 0.0;

    /** Pieds totaux saisis (avant répartition par effectif) */
    #[ORM\Column(options: ['default' => 0])]
    private float $piedsTotal = 0.0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    /** Terrain | RH */
    #[ORM\Column(length: 20, options: ['default' => 'Terrain'])]
    private string $type = 'Terrain';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $creeA = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $modifieA = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $nombreModifs = 0;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getDateTravail(): ?\DateTimeInterface
    {
        return $this->dateTravail;
    }

    public function setDateTravail(\DateTimeInterface $dateTravail): static
    {
        $this->dateTravail = $dateTravail;
        return $this;
    }

    public function getMois(): ?string
    {
        return $this->mois;
    }

    public function setMois(string $mois): static
    {
        $this->mois = $mois;
        return $this;
    }

    public function getChefNom(): ?string
    {
        return $this->chefNom;
    }

    public function setChefNom(string $chefNom): static
    {
        $this->chefNom = $chefNom;
        return $this;
    }

    public function getPersonnelNom(): ?string
    {
        return $this->personnelNom;
    }

    public function setPersonnelNom(string $personnelNom): static
    {
        $this->personnelNom = $personnelNom;
        return $this;
    }

    public function getPersonnelContrat(): ?string
    {
        return $this->personnelContrat;
    }

    public function setPersonnelContrat(?string $personnelContrat): static
    {
        $this->personnelContrat = $personnelContrat;
        return $this;
    }

    public function getTacheNom(): ?string
    {
        return $this->tacheNom;
    }

    public function setTacheNom(string $tacheNom): static
    {
        $this->tacheNom = $tacheNom;
        return $this;
    }

    public function getParcelleNom(): ?string
    {
        return $this->parcelleNom;
    }

    public function setParcelleNom(?string $parcelleNom): static
    {
        $this->parcelleNom = $parcelleNom;
        return $this;
    }

    public function getHeures(): float
    {
        return $this->heures;
    }

    public function setHeures(float $heures): static
    {
        $this->heures = $heures;
        return $this;
    }

    public function getHeuresNettes(): float
    {
        return $this->heuresNettes;
    }

    public function setHeuresNettes(float $heuresNettes): static
    {
        $this->heuresNettes = $heuresNettes;
        return $this;
    }

    public function getEffectif(): int
    {
        return $this->effectif;
    }

    public function setEffectif(int $effectif): static
    {
        $this->effectif = max(1, $effectif);
        return $this;
    }

    public function getModePause(): string
    {
        return $this->modePause;
    }

    public function setModePause(string $modePause): static
    {
        $this->modePause = $modePause;
        return $this;
    }

    public function getMinutesPause(): int
    {
        return $this->minutesPause;
    }

    public function setMinutesPause(int $minutesPause): static
    {
        $this->minutesPause = $minutesPause;
        return $this;
    }

    public function getAvancement(): float
    {
        return $this->avancement;
    }

    public function setAvancement(float $avancement): static
    {
        $this->avancement = max(0.0, min(100.0, $avancement));
        return $this;
    }

    public function getPieds(): float
    {
        return $this->pieds;
    }

    public function setPieds(float $pieds): static
    {
        $this->pieds = $pieds;
        return $this;
    }

    public function getPiedsTotal(): float
    {
        return $this->piedsTotal;
    }

    public function setPiedsTotal(float $piedsTotal): static
    {
        $this->piedsTotal = $piedsTotal;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getModifieA(): ?\DateTimeInterface
    {
        return $this->modifieA;
    }

    public function setModifieA(?\DateTimeInterface $modifieA): static
    {
        $this->modifieA = $modifieA;
        return $this;
    }

    public function getNombreModifs(): int
    {
        return $this->nombreModifs;
    }

    public function setNombreModifs(int $nombreModifs): static
    {
        $this->nombreModifs = $nombreModifs;
        return $this;
    }

    /** Calcule le rendement pieds/heure nette (null si pas de données) */
    public function getRendement(): ?float
    {
        if ($this->heuresNettes <= 0 || $this->pieds <= 0) {
            return null;
        }
        return $this->pieds / $this->heuresNettes;
    }

    public function isRH(): bool
    {
        return $this->type === 'RH';
    }
}
