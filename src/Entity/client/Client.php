<?php

namespace App\Entity\client;

use App\Repository\client\ClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: "client", schema: "client")]
class Client
{
    #[ORM\Id]
    #[ORM\Column(name: '"UNIQUEID"', type: "integer", nullable: false)]
    private ?int $uniqueId = null;

    #[ORM\Column(name: "date_envoi", type: "string", length: 128, nullable: true)]
    private ?string $dateEnvoi = null;

    #[ORM\Column(name: "signataires", type: "string", length: 512, nullable: true)]
    private ?string $signataires = null;

    #[ORM\Column(name: "prioritéfe", type: "string", length: 128, nullable: true)]
    private ?string $prioriteFe = null;

    #[ORM\Column(name: "société_nom", type: "string", length: 128, nullable: true)]
    private ?string $societeNom = null;

    #[ORM\ManyToOne(targetEntity: Categorie::class)]
    #[ORM\JoinColumn(name: "catégorie_id", referencedColumnName: "id", nullable: true)]
    private ?Categorie $categorie = null;

    #[ORM\Column(name: "commentaires", type: "string", length: 128, nullable: true)]
    private ?string $commentaires = null;

    #[ORM\Column(name: "code_app", type: "string", length: 128, nullable: true)]
    private ?string $codeApp = null;

    #[ORM\Column(name: "prénom_nom", type: "string", length: 128, nullable: true)]
    private ?string $prenomNom = null;

    #[ORM\Column(name: "prénom", type: "string", length: 128, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(name: "nom", type: "string", length: 128, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(name: "adresse1", type: "string", length: 128, nullable: true)]
    private ?string $adresse1 = null;

    #[ORM\Column(name: "adresse2", type: "string", length: 128, nullable: true)]
    private ?string $adresse2 = null;

    #[ORM\Column(name: "code_postal", type: "string", length: 128, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(name: "ville", type: "string", length: 128, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: "pays", type: "string", length: 128, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(name: "langue", type: "string", length: 128, nullable: true)]
    private ?string $langue = null;

    // Getters and Setters

    public function getUniqueId(): ?int
    {
        return $this->uniqueId;
    }

    public function setUniqueId(?int $uniqueId): self
    {
        $this->uniqueId = $uniqueId;
        return $this;
    }

    public function getDateEnvoi(): ?string
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?string $dateEnvoi): self
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getSignataires(): ?string
    {
        return $this->signataires;
    }

    public function setSignataires(?string $signataires): self
    {
        $this->signataires = $signataires;
        return $this;
    }

    public function getPrioriteFe(): ?string
    {
        return $this->prioriteFe;
    }

    public function setPrioriteFe(?string $prioriteFe): self
    {
        $this->prioriteFe = $prioriteFe;
        return $this;
    }

    public function getSocieteNom(): ?string
    {
        return $this->societeNom;
    }

    public function setSocieteNom(?string $societeNom): self
    {
        $this->societeNom = $societeNom;
        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }


    public function getCommentaires(): ?string
    {
        return $this->commentaires;
    }

    public function setCommentaires(?string $commentaires): self
    {
        $this->commentaires = $commentaires;
        return $this;
    }

    public function getCodeApp(): ?string
    {
        return $this->codeApp;
    }

    public function setCodeApp(?string $codeApp): self
    {
        $this->codeApp = $codeApp;
        return $this;
    }

    public function getPrenomNom(): ?string
    {
        return $this->prenomNom;
    }

    public function setPrenomNom(?string $prenomNom): self
    {
        $this->prenomNom = $prenomNom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getAdresse1(): ?string
    {
        return $this->adresse1;
    }

    public function setAdresse1(?string $adresse1): self
    {
        $this->adresse1 = $adresse1;
        return $this;
    }

    public function getAdresse2(): ?string
    {
        return $this->adresse2;
    }

    public function setAdresse2(?string $adresse2): self
    {
        $this->adresse2 = $adresse2;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): self
    {
        $this->pays = $pays;
        return $this;
    }

    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(?string $langue): self
    {
        $this->langue = $langue;
        return $this;
    }
}
