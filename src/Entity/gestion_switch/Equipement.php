<?php

namespace App\Entity\gestion_switch;

use App\Repository\gestion_switch\EquipementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Switch ou cluster Wi-Fi interrogé par Gestion switch (os = instant pour un cluster Aruba Instant).
 * Les champs null prennent la valeur par défaut de config/aruba/config.json (defaults).
 * Les mots de passe ne sont pas en base : config/aruba/secrets/<code>.txt (chiffrés AES).
 */
#[ORM\Entity(repositoryClass: EquipementRepository::class)]
#[ORM\Table(name: 'equipement', schema: 'gestion_switch')]
#[ORM\UniqueConstraint(name: 'equipement_code_key', columns: ['code'])]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant utilisé dans l'interface et les noms des secrets ('coeur', 'wifi-chateau'…) */
    #[ORM\Column(length: 50)]
    private string $code = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private string $hote = '';

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $site = null;

    /** aos-cx | aos-switch | instant */
    #[ORM\Column(length: 20, options: ['default' => 'aos-cx'])]
    private string $os = 'aos-cx';

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $protocole = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $apiVersion = null;

    #[ORM\Column(nullable: true)]
    private ?bool $verifySsl = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeout = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $utilisateur = null;

    #[ORM\Column]
    private int $position = 0;

    /** false : conservé dans la configuration mais plus interrogé */
    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    // ── dernière identification connue (mise à jour à chaque collecte) ──

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $hostname = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modele = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(nullable: true, columnDefinition: 'MACADDR')]
    private ?string $macSysteme = null;

    /** UTC */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $derniereCollecte = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $derniereErreur = null;

    // ── chiffres de la dernière collecte réussie ──

    #[ORM\Column(nullable: true)]
    private ?int $portsTotal = null;

    #[ORM\Column(nullable: true)]
    private ?int $portsActifs = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbMac = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbVoisins = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbVlans = null;

    /** version d'API REST utilisée (v10.09…) */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $apiDetectee = null;

    /**
     * Entrée au format de l'ancien config.json (clés id, host, name…), sans les valeurs non renseignées :
     * c'est le tableau manipulé par App\Service\Aruba\Config, le Collector et l'écran Configuration.
     */
    public function toConfig(): array
    {
        return [
            'id' => $this->code,
            'host' => $this->hote,
            'name' => (string) $this->nom,
            'site' => (string) $this->site,
            'os' => $this->os,
            'actif' => $this->actif,
        ] + array_filter([
            'protocol' => $this->protocole,
            'username' => $this->utilisateur,
            'timeout' => $this->timeout,
            'api_version' => $this->apiVersion,
            'verify_ssl' => $this->verifySsl,
        ], fn($v) => $v !== null);
    }

    /** Inverse de toConfig() : les valeurs vides ou absentes reviennent aux valeurs par défaut. */
    public function fromConfig(array $sw): static
    {
        $str = fn(string $k) => isset($sw[$k]) && trim((string) $sw[$k]) !== '' ? trim((string) $sw[$k]) : null;
        $this->hote = (string) $str('host');
        $this->nom = $str('name');
        $this->site = $str('site');
        $this->os = $str('os') ?? 'aos-cx';
        $this->protocole = $str('protocol');
        $this->utilisateur = $str('username');
        $this->apiVersion = $str('api_version');
        $this->timeout = isset($sw['timeout']) && $sw['timeout'] !== '' ? (int) $sw['timeout'] : null;
        $this->verifySsl = isset($sw['verify_ssl']) && $sw['verify_ssl'] !== '' ? (bool) $sw['verify_ssl'] : null;
        $this->actif = (bool) ($sw['actif'] ?? true);
        return $this;
    }

    public function isWifi(): bool
    {
        return $this->os === 'instant';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getHote(): string
    {
        return $this->hote;
    }

    public function setHote(string $hote): static
    {
        $this->hote = $hote;
        return $this;
    }

    public function getSite(): ?string
    {
        return $this->site;
    }

    public function setSite(?string $site): static
    {
        $this->site = $site;
        return $this;
    }

    public function getOs(): string
    {
        return $this->os;
    }

    public function setOs(string $os): static
    {
        $this->os = $os;
        return $this;
    }

    public function getProtocole(): ?string
    {
        return $this->protocole;
    }

    public function setProtocole(?string $protocole): static
    {
        $this->protocole = $protocole;
        return $this;
    }

    public function getApiVersion(): ?string
    {
        return $this->apiVersion;
    }

    public function setApiVersion(?string $apiVersion): static
    {
        $this->apiVersion = $apiVersion;
        return $this;
    }

    public function getVerifySsl(): ?bool
    {
        return $this->verifySsl;
    }

    public function setVerifySsl(?bool $verifySsl): static
    {
        $this->verifySsl = $verifySsl;
        return $this;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setTimeout(?int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function getUtilisateur(): ?string
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?string $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getMacSysteme(): ?string
    {
        return $this->macSysteme;
    }

    public function getDerniereCollecte(): ?\DateTimeImmutable
    {
        return $this->derniereCollecte;
    }

    public function getDerniereErreur(): ?string
    {
        return $this->derniereErreur;
    }

    public function getPortsTotal(): ?int
    {
        return $this->portsTotal;
    }

    public function getPortsActifs(): ?int
    {
        return $this->portsActifs;
    }

    public function getNbMac(): ?int
    {
        return $this->nbMac;
    }

    public function getNbVoisins(): ?int
    {
        return $this->nbVoisins;
    }

    public function getNbVlans(): ?int
    {
        return $this->nbVlans;
    }

    public function getApiDetectee(): ?string
    {
        return $this->apiDetectee;
    }
}
