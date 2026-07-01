<?php

namespace App\Entity\tracabilite;

use App\Repository\tracabilite\JournalModificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal d'audit : trace toutes les créations, modifications et suppressions de saisies.
 */
#[ORM\Entity(repositoryClass: JournalModificationRepository::class)]
#[ORM\Table(name: 'vigne_journal')]
#[ORM\Index(fields: ['saisieId'], name: 'idx_journal_saisie')]
#[ORM\Index(fields: ['effectueA'], name: 'idx_journal_date')]
class JournalModification
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private ?string $id = null;

    /** Création | Modification | Suppression */
    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $effectueA = null;

    /** UUID de la saisie concernée */
    #[ORM\Column(length: 36)]
    private ?string $saisieId = null;

    /** Date de la saisie concernée (dénormalisée pour recherche rapide) */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $saisieDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $chefNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $personnelNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tacheNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parcelleNom = null;

    /** Snapshot JSON de la saisie avant modification */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $avant = null;

    /** Snapshot JSON de la saisie après modification */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $apres = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getEffectueA(): ?\DateTimeImmutable
    {
        return $this->effectueA;
    }

    public function setEffectueA(\DateTimeImmutable $effectueA): static
    {
        $this->effectueA = $effectueA;
        return $this;
    }

    public function getSaisieId(): ?string
    {
        return $this->saisieId;
    }

    public function setSaisieId(string $saisieId): static
    {
        $this->saisieId = $saisieId;
        return $this;
    }

    public function getSaisieDate(): ?string
    {
        return $this->saisieDate;
    }

    public function setSaisieDate(?string $saisieDate): static
    {
        $this->saisieDate = $saisieDate;
        return $this;
    }

    public function getChefNom(): ?string
    {
        return $this->chefNom;
    }

    public function setChefNom(?string $chefNom): static
    {
        $this->chefNom = $chefNom;
        return $this;
    }

    public function getPersonnelNom(): ?string
    {
        return $this->personnelNom;
    }

    public function setPersonnelNom(?string $personnelNom): static
    {
        $this->personnelNom = $personnelNom;
        return $this;
    }

    public function getTacheNom(): ?string
    {
        return $this->tacheNom;
    }

    public function setTacheNom(?string $tacheNom): static
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

    public function getAvant(): ?string
    {
        return $this->avant;
    }

    public function setAvant(?string $avant): static
    {
        $this->avant = $avant;
        return $this;
    }

    public function getApres(): ?string
    {
        return $this->apres;
    }

    public function setApres(?string $apres): static
    {
        $this->apres = $apres;
        return $this;
    }
}
