<?php

namespace App\Entity\client;

use App\Repository\client\LiaisonSignatairesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LiaisonSignatairesRepository::class)]
#[ORM\Table(name: "liaison_signataires", schema: "client")]
class LiaisonSignataires
{
    #[ORM\Column(name: "signature", type: "boolean", nullable: true)]
    private ?bool $signature = null;

    #[ORM\Column(name: "conserver", type: "boolean", nullable: true)]
    private ?bool $conserver = null;

    #[ORM\Column(name: "envoi_mail", type: "boolean", nullable: true)]
    private ?bool $envoiMail = null;

    #[ORM\Id]
    #[ORM\Column(name: '"UNIQUEID"', type: "integer", nullable: false)]
    private ?int $uniqueid = null;

    #[ORM\Id]
    #[ORM\Column(type: "text", nullable: false)]
    private ?string $signataire = null;

    // Getters and Setters

    public function getUniqueid(): ?int
    {
        return $this->uniqueid;
    }

    public function setUniqueid(?int $uniqueid): self
    {
        $this->uniqueid = $uniqueid;
        return $this;
    }

    public function getSignataire(): ?string
    {
        return $this->signataire;
    }

    public function setSignataire(?string $signataire): self
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
