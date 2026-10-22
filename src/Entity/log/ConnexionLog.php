<?php

namespace App\Entity\log;

use App\Repository\log\ConnexionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal des connexions : connexions réussies, échecs et déconnexions.
 * Alimenté par App\EventSubscriber\ActivityLogSubscriber.
 */
#[ORM\Entity(repositoryClass: ConnexionLogRepository::class)]
#[ORM\Table(name: 'app_log_connexion')]
#[ORM\Index(fields: ['creeLe'], name: 'idx_log_connexion_date')]
#[ORM\Index(fields: ['email'], name: 'idx_log_connexion_email')]
class ConnexionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** connexion | echec | deconnexion */
    #[ORM\Column(length: 20)]
    private ?string $evenement = null;

    /** formulaire | microsoft | remember_me */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $methode = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    /** Empreinte de la session (jamais l'identifiant brut) */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $session = null;

    /** Raison de l'échec de connexion */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $creeLe = null;

    public function getId(): ?int { return $this->id; }
    public function getEvenement(): ?string { return $this->evenement; }
    public function getMethode(): ?string { return $this->methode; }
    public function getEmail(): ?string { return $this->email; }
    public function getUserId(): ?int { return $this->userId; }
    public function getIp(): ?string { return $this->ip; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function getSession(): ?string { return $this->session; }
    public function getMotif(): ?string { return $this->motif; }
    public function getCreeLe(): ?\DateTimeImmutable { return $this->creeLe; }
}
