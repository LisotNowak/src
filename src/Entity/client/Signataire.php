<?php

namespace App\Entity\client;

use App\Repository\client\SignataireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignataireRepository::class)]
#[ORM\Table(name: "client.signatairev2", schema: "client")]
class Signataire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "nom", type: "string", length: 255, unique: true)]
    private ?string $nom = null;

    // Getter pour id
    public function getId(): ?int
    {
        return $this->id;
    }

    // Getter pour nom
    public function getNom(): ?string
    {
        return $this->nom;
    }

    // Setter pour nom
    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }
}
