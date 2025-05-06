<?php

namespace App\Entity\dotation;

use App\Repository\dotation\CommandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: "commande", schema: "dotation")]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $userMail = null;

    #[ORM\Column(length: 255)]
    private ?string $date = null;

    #[ORM\Column(length: 255)]
    private ?string $nomEtat = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserMail(): ?string
    {
        return $this->userMail;
    }

    public function setUserMail(string $userMail): static
    {
        $this->userMail = $userMail;

        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(string $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getNomEtat(): ?string
    {
        return $this->nomEtat;
    }

    public function setNomEtat(string $nomEtat): static
    {
        $this->nomEtat = $nomEtat;

        return $this;
    }

    
}
