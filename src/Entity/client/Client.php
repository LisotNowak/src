<?php

namespace App\Entity\client;

use App\Repository\client\ClientRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: "clientv2", schema: "client")]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "id", type: "integer", nullable: false)]
    private ?int $id = null;

    #[ORM\Column(name: '"Date d\'envoi"', type: "string", length: 150, nullable: true)]
    private ?string $dateEnvoi = null;

    #[ORM\Column(name: '"Signataires"', type: "string", length: 150, nullable: true)]
    private ?string $signataires = null;

    #[ORM\Column(name: '"Priorité FE"', type: "string", length: 150, nullable: true)]
    private ?string $prioriteFe = null;

    #[ORM\Column(name: '"SociétéNom"', type: "string", length: 150, nullable: true)]
    private ?string $societeNom = null;

    #[ORM\Column(name: '"Catégorie"', type: "string", length: 150, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(name: '"Commentaires"', type: "string", length: 150, nullable: true)]
    private ?string $commentaires = null;

    #[ORM\Column(name: '"Code App."', type: "string", length: 150, nullable: true)]
    private ?string $codeApp = null;

    #[ORM\Column(name: '"PrénomNom Enveloppe"', type: "string", length: 150, nullable: true)]
    private ?string $prenomNomEnveloppe = null;

    #[ORM\Column(name: '"TriPrénom"', type: "string", length: 150, nullable: true)]
    private ?string $triPrenom = null;

    #[ORM\Column(name: '"TriNom"', type: "string", length: 150, nullable: true)]
    private ?string $triNom = null;

    #[ORM\Column(name: '"Adresse 1"', type: "string", length: 150, nullable: true)]
    private ?string $adresse1 = null;

    #[ORM\Column(name: '"Adresse 2"', type: "string", length: 150, nullable: true)]
    private ?string $adresse2 = null;

    #[ORM\Column(name: '"Code postal"', type: "string", length: 150, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(name: '"Ville"', type: "string", length: 150, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: '"Pays"', type: "string", length: 150, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(name: '"Langue"', type: "string", length: 150, nullable: true)]
    private ?string $langue = null;

    #[ORM\ManyToOne(targetEntity: Categorie::class)]
    #[ORM\JoinColumn(name: "catégorie_id", referencedColumnName: "id", nullable: true)]
    private ?Categorie $categorieEntity = null;

    #[ORM\ManyToOne(targetEntity: Signataire::class)]
    #[ORM\JoinColumn(name: "signataire_id", referencedColumnName: "id", nullable: true)]
    private ?Signataire $signataireEntity = null;

    #[ORM\Column(name: "signature", type: "boolean", nullable: true)]
    private ?bool $signature = null;

    #[ORM\Column(name: "conserver", type: "boolean", nullable: true)]
    private ?bool $conserver = null;

    #[ORM\OneToMany(mappedBy: "client", targetEntity: AssociationSignataire::class)]
    private Collection $associations;

    public function __construct()
    {
        $this->associations = new ArrayCollection();
    }

    // ... tous les getters et setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
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

    public function getTriPrenom(): ?string
    {
        return $this->triPrenom;
    }

    public function setTriPrenom(?string $triPrenom): self
    {
        $this->triPrenom = $triPrenom;
        return $this;
    }

    public function getTriNom(): ?string
    {
        return $this->triNom;
    }

    public function setTriNom(?string $triNom): self
    {
        $this->triNom = $triNom;
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

    public function getSignataire(): ?Signataire
    {
        return $this->signataire;
    }

    public function setSignataire(?Signataire $signataire): self
    {
        $this->signataire = $signataire;
        return $this;
    }

    public function getSignature(): ?bool
    {
        return $this->signature;
    }

    public function setSignature(?bool $signature): self
    {
        $this->signature = $signature;
        return $this;
    }

    public function getConserver(): ?bool
    {
        return $this->conserver;
    }

    public function setConserver(?bool $conserver): self
    {
        $this->conserver = $conserver;
        return $this;
    }

    public function getCategorieEntity(): ?Categorie
    {
        return $this->categorieEntity;
    }

    public function setCategorieEntity(?Categorie $categorieEntity): self
    {
        $this->categorieEntity = $categorieEntity;
        return $this;
    }

}