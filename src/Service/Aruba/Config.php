<?php
/**
 * Configuration : switchs et clusters Wi-Fi en base (table gestion_switch.equipement), paramètres communs
 * dans config/aruba/config.json (parallel, defaults, other_switches, glpi, retention_days, auto_collect,
 * auto_interval) + identifiants.
 * Le tout se modifie depuis l'écran Gestion switch > Configuration (App\Controller\Aruba\ArubaAdminController).
 * Les switchs circulent sous forme de tableau (clés id, host, name… de l'ancien config.json, voir
 * Equipement::toConfig()). Les utilisateurs sont en base (username du switch) ou dans config.json
 * (defaults.username). Les mots de passe sont
 * chiffrés en AES dans config/aruba/secrets (format ConvertFrom-SecureString -Key de PowerShell, aussi
 * écrit par `bin/console aruba:password`). Les variables ARUBA_USER / ARUBA_PASSWORD (et _<ID>) des
 * fichiers .env restent acceptées et sont prioritaires. Un fichier .env dédié reste possible ($envFile),
 * il est alors relu à chaque appel de env() : on peut le modifier sans redémarrer.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

use App\Entity\gestion_switch\Equipement;
use App\Repository\gestion_switch\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;

class Config
{
    private array $cfg;

    /** @var Equipement[]|null lus une fois par requête (voir equipements()) */
    private ?array $equipements = null;

    public function __construct(
        private string $configFile,
        private EntityManagerInterface $em,
        private EquipementRepository $equipementRepository,
        private string $envFile = '',
    ) {
        $this->reload();
    }

    /** Relit config.json (processus qui tourne en continu : collecte automatique). */
    public function reload(): void
    {
        if (!is_file($this->configFile)) {
            throw new \RuntimeException("Fichier de configuration introuvable : {$this->configFile}\n"
                . 'Copiez config/aruba/config.example.json en config.json et renseignez vos switchs.');
        }
        $cfg = json_decode((string) file_get_contents($this->configFile), true);
        if (!is_array($cfg)) {
            throw new \RuntimeException("config.json invalide : " . json_last_error_msg());
        }
        $this->cfg = $cfg;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cfg[$key] ?? $default;
    }

    /** Lit le fichier .env (CLE=valeur, # pour les commentaires). */
    public function readDotenv(): array
    {
        $out = [];
        if ($this->envFile === '' || !is_file($this->envFile)) {
            return $out;
        }
        $text = (string) file_get_contents($this->envFile);
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if (strlen($v) >= 2 && $v[0] === $v[-1] && ($v[0] === '"' || $v[0] === "'")) {
                $v = substr($v, 1, -1);
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /** Valeur du .env, sinon variable d'environnement (utile sous Symfony : .env.local, secrets…). */
    public function env(string $key, ?array $dotenv = null): string
    {
        $dotenv ??= $this->readDotenv();
        return (string) (($dotenv[$key] ?? '') ?: (($_ENV[$key] ?? '') ?: (($_SERVER[$key] ?? '') ?: getenv($key))));
    }

    private static function idOf(array $s): string
    {
        return str_replace(' ', '_', (string) (($s['id'] ?? '') ?: (($s['name'] ?? '') ?: $s['host'])));
    }

    /** @return Equipement[] dans l'ordre d'affichage */
    private function equipements(): array
    {
        return $this->equipements ??= $this->equipementRepository->findOrdered();
    }

    private function equipement(string $id): ?Equipement
    {
        foreach ($this->equipements() as $e) {
            if ($e->getCode() === $id) {
                return $e;
            }
        }
        return null;
    }

    /** Tous les switchs et clusters Wi-Fi, désactivés compris (clé actif), complétés par les valeurs par défaut. */
    public function switches(): array
    {
        $defaults = $this->cfg['defaults'] ?? [];
        return array_map(fn(Equipement $e) => array_merge($defaults, $e->toConfig()), $this->equipements());
    }

    /** Switch tel qu'enregistré (sans les valeurs par défaut), null si inconnu. */
    public function rawSwitch(string $id): ?array
    {
        return $this->equipement($id)?->toConfig();
    }

    public function switch(string $id): array
    {
        foreach ($this->switches() as $sw) {
            if ($sw['id'] === $id) {
                return $sw;
            }
        }
        throw new \RuntimeException("Switch inconnu : {$id}");
    }

    /** @return array{0:string,1:string} utilisateur, mot de passe */
    public function credentials(array $sw): array
    {
        $env = $this->readDotenv();
        $suffix = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '_', $sw['id']));
        // utilisateur du switch (config.json), sinon commun (ARUBA_USER puis defaults.username)
        $user = $this->env("ARUBA_USER_{$suffix}", $env) ?: (($this->rawSwitch($sw['id'])['username'] ?? '')
            ?: ($this->env('ARUBA_USER', $env) ?: ($sw['username'] ?? '')));
        // propre au switch (variable puis fichier chiffré), sinon commun à tous (variable puis fichier chiffré)
        $pwd = $this->env("ARUBA_PASSWORD_{$suffix}", $env)
            ?: ($this->encryptedPassword($sw['password_file'] ?? $this->secretFile($sw['id']), $sw['key_file'] ?? null)
            ?: ($this->env('ARUBA_PASSWORD', $env)
            ?: ($this->encryptedPassword($this->secretFile('default'))
            ?: ($sw['password'] ?? ''))));
        if (!$pwd) {
            throw new \RuntimeException('Mot de passe absent : renseignez-le dans Gestion switch > Configuration '
                . '(ou « php bin/console aruba:password » ) puis cliquez sur Actualiser');
        }
        if (!$user) {
            throw new \RuntimeException('Utilisateur absent : renseignez-le dans Gestion switch > Configuration');
        }
        return [$user, $pwd];
    }

    /** Mot de passe chiffré par aruba:password : secrets/<id>.txt, secrets/default.txt pour tous les switchs. */
    public function secretFile(string $id): string
    {
        return 'secrets/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $id) . '.txt';
    }

    public function keyFile(): string
    {
        return $this->path((string) $this->get('key_file', 'secrets/aruba.AES.key'));
    }

    /** Déchiffre le fichier s'il existe ('' sinon). */
    private function encryptedPassword(string $passwordFile, ?string $keyFile = null): string
    {
        $passwordFile = $this->path($passwordFile);
        if (!is_file($passwordFile)) {
            return '';
        }
        return SecurePassword::read($passwordFile, $keyFile !== null ? $this->path($keyFile) : $this->keyFile());
    }

    // ------------------------------------------------------------ modification (écran Configuration)
    public function hasOwnPassword(string $id): bool
    {
        return is_file($this->path($this->secretFile($id)));
    }

    /** Chiffre le mot de passe du switch ($id = 'default' : mot de passe commun). */
    public function setPassword(string $id, string $password): void
    {
        SecurePassword::write($password, $this->path($this->secretFile($id)), $this->keyFile());
    }

    public function removePassword(string $id): void
    {
        $file = $this->path($this->secretFile($id));
        if (is_file($file)) {
            unlink($file);
        }
    }

    /** Ajoute (en dernière position) ou remplace un switch (les clés vides reviennent aux valeurs par défaut). */
    public function saveSwitch(array $sw): void
    {
        $e = $this->equipement($sw['id']);
        if ($e === null) {
            $last = max([0, ...array_map(fn(Equipement $x) => $x->getPosition(), $this->equipements())]);
            $e = (new Equipement())->setCode($sw['id'])->setPosition($last + 1);
            $this->em->persist($e);
        }
        $e->fromConfig($sw);
        $this->em->flush();
        $this->equipements = null;
    }

    public function deleteSwitch(string $id): void
    {
        if ($e = $this->equipement($id)) {
            // en base : résultats de collecte supprimés, appareils détachés (ON DELETE CASCADE / SET NULL)
            $this->em->remove($e);
            $this->em->flush();
            $this->equipements = null;
        }
        $this->removePassword($id);
    }

    /** Déplace un switch d'un rang ($delta = -1 : vers le haut, 1 : vers le bas). */
    public function moveSwitch(string $id, int $delta): void
    {
        $list = $this->equipements();
        foreach ($list as $i => $e) {
            $e->setPosition($i + 1);   // renumérotation : positions uniques et continues
        }
        foreach ($list as $i => $e) {
            if ($e->getCode() !== $id) {
                continue;
            }
            // échange avec le voisin de même nature (switchs et clusters Wi-Fi sont listés à part)
            for ($j = $i + $delta; isset($list[$j]); $j += $delta) {
                if ($list[$j]->isWifi() === $e->isWifi()) {
                    $e->setPosition($j + 1);
                    $list[$j]->setPosition($i + 1);
                    break;
                }
            }
            break;
        }
        $this->em->flush();
        $this->equipements = null;
    }

    // ------------------------------------------------------------ reprise de l'ancien config.json
    /** Switchs encore présents dans config.json (avant leur passage en base par aruba:import). */
    public function fileSwitches(): array
    {
        return array_map(fn(array $s) => ['id' => self::idOf($s)] + $s, $this->cfg['switches'] ?? []);
    }

    /** Retire la liste des switchs de config.json (copie de l'ancien fichier dans config.json.avant-bdd). */
    public function removeFileSwitches(): void
    {
        if (!isset($this->cfg['switches'])) {
            return;
        }
        copy($this->configFile, $this->configFile . '.avant-bdd');
        unset($this->cfg['switches']);
        $this->write();
    }

    // ------------------------------------------------------------ GLPI
    /**
     * Connexion GLPI (clé "glpi" de config.json : url, client_id, username, enabled), null si non configurée.
     * Secrets chiffrés : secrets/glpi-client.txt (secret du client OAuth), secrets/glpi.txt (mot de passe).
     */
    public function glpi(): ?array
    {
        $g = $this->cfg['glpi'] ?? null;
        return is_array($g) && ($g['url'] ?? '') !== '' ? $g : null;
    }

    public function saveGlpi(?array $glpi): void
    {
        if ($glpi === null) {
            unset($this->cfg['glpi']);
            $this->removePassword('glpi');
            $this->removePassword('glpi-client');
        } else {
            $this->cfg['glpi'] = $glpi;
        }
        $this->write();
    }

    /** Secret chiffré par setPassword() ('' s'il n'existe pas). */
    public function secret(string $id): string
    {
        return $this->encryptedPassword($this->secretFile($id));
    }

    /** Paramètres généraux : parallel, defaults, other_switches. */
    public function saveSettings(array $settings): void
    {
        $this->cfg = array_merge($this->cfg, $settings);
        $this->write();
    }

    private function write(): void
    {
        $json = json_encode($this->cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $tmp = $this->configFile . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException("Écriture impossible : {$this->configFile}");
        }
        // sous Windows, le remplacement échoue si une collecte lit le fichier à cet instant
        for ($i = 0; $i < 20; $i++) {
            if (@rename($tmp, $this->configFile)) {
                return;
            }
            usleep(50_000);
        }
        @unlink($tmp);
        throw new \RuntimeException("Écriture impossible : {$this->configFile}");
    }

    /** Chemin relatif au dossier de config.json. */
    public function path(string $p): string
    {
        return preg_match('#^([a-zA-Z]:)?[\\\\/]#', $p) ? $p : dirname($this->configFile) . DIRECTORY_SEPARATOR . $p;
    }
}
