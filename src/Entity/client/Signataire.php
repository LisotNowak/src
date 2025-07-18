<?php

namespace App\Entity\client;

use App\Repository\client\SignataireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignataireRepository::class)]
#[ORM\Table(name: "signataire", schema: "client")]
class Signataire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "text", unique: true)]
    private ?string $signataire = null;

    // Getter pour id
    public function getId(): ?int
    {
        return $this->id;
    }

    // Getter pour signataire
    public function getSignataire(): ?string
    {
        return $this->signataire;
    }

    // Setter pour signataire
    public function setSignataire(string $signataire): self
    {
        $this->signataire = $signataire;
        return $this;
    }
}
