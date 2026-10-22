<?php

namespace App\Entity\log;

use App\Repository\log\PageVisiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une page vue (ou une action POST/export) par un utilisateur connecté.
 * Le temps actif est renvoyé par le navigateur (sendBeacon) en quittant la page.
 */
#[ORM\Entity(repositoryClass: PageVisiteRepository::class)]
#[ORM\Table(name: 'app_log_page')]
#[ORM\Index(fields: ['visiteLe'], name: 'idx_log_page_date')]
#[ORM\Index(fields: ['userId'], name: 'idx_log_page_user')]
#[ORM\Index(fields: ['route'], name: 'idx_log_page_route')]
class PageVisite
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private ?string $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    /** Empreinte de la session (jamais l'identifiant brut) */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $session = null;

    #[ORM\Column(length: 10)]
    private ?string $methodeHttp = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(length: 500)]
    private ?string $chemin = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $statut = null;

    /** Temps de génération de la page côté serveur */
    #[ORM\Column(nullable: true)]
    private ?int $dureeServeurMs = null;

    /** Temps passé sur la page, onglet visible et utilisateur actif */
    #[ORM\Column(nullable: true)]
    private ?int $tempsActifS = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $visiteLe = null;

    public function getId(): ?string { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function getEmail(): ?string { return $this->email; }
    public function getSession(): ?string { return $this->session; }
    public function getMethodeHttp(): ?string { return $this->methodeHttp; }
    public function getRoute(): ?string { return $this->route; }
    public function getChemin(): ?string { return $this->chemin; }
    public function getStatut(): ?int { return $this->statut; }
    public function getDureeServeurMs(): ?int { return $this->dureeServeurMs; }
    public function getTempsActifS(): ?int { return $this->tempsActifS; }
    public function getIp(): ?string { return $this->ip; }
    public function getVisiteLe(): ?\DateTimeImmutable { return $this->visiteLe; }
}
