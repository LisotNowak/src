<?php

namespace App\Entity\client;

use App\Repository\dotation\LiaisonSignatairesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LiaisonSignatairesRepository::class)]
#[ORM\Table(name: "liaison_signataires", schema: "client")]
class LiaisonSignataires
{
    #[ORM\Id]
    #[ORM\Column(name: "uniqueid", type: "integer", nullable: true)]
    private ?int $uniqueid = null;

    #[ORM\Column(type: "text", nullable: true)]
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
}
