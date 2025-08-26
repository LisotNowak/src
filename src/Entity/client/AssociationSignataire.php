<?php

namespace App\Entity\client;

use App\Repository\client\AssociationSignataireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssociationSignataireRepository::class)]
#[ORM\Table(name: "association_signataire", schema: "client")]
class AssociationSignataire
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id", type: "integer", nullable: false)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Signataire::class)]
    #[ORM\JoinColumn(name: "signataire_id", referencedColumnName: "id", nullable: true)]
    private ?Signataire $signataire = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: "associations")]
    #[ORM\JoinColumn(name: "client_id", referencedColumnName: "id", nullable: true)]
    private ?Client $client = null;

    #[ORM\Column(name: "signature", type: "boolean", nullable: true)]
    private ?bool $signature = null;

    #[ORM\Column(name: "conserver", type: "boolean", nullable: true)]
    private ?bool $conserver = null;

    #[ORM\Column(name: "envoi_mail", type: "boolean", nullable: true)]
    private ?bool $envoiMail = null;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
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

    public function getEnvoiMail(): ?bool
    {
        return $this->envoiMail;
    }

    public function setEnvoiMail(?bool $envoiMail): self
    {
        $this->envoiMail = $envoiMail;
        return $this;
    }
}
