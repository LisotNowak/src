<?php
/**
 * Configuration : config/aruba/config.json (switchs, options) + identifiants.
 * Les identifiants (ARUBA_USER, ARUBA_PASSWORD, ARUBA_PASSWORD_<ID>) sont lus dans les variables
 * d'environnement de Symfony (.env / .env.local) ; un fichier .env dédié reste possible ($envFile),
 * il est alors relu à chaque appel de env() : on peut le modifier sans redémarrer.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class Config
{
    private array $cfg;

    public function __construct(private string $configFile, private string $envFile = '')
    {
        if (!is_file($configFile)) {
            throw new \RuntimeException("Fichier de configuration introuvable : {$configFile}\n"
                . 'Copiez config/aruba/config.example.json en config.json et renseignez vos switchs.');
        }
        $cfg = json_decode((string) file_get_contents($configFile), true);
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

    public function switches(): array
    {
        $defaults = $this->cfg['defaults'] ?? [];
        $out = [];
        foreach ($this->cfg['switches'] ?? [] as $s) {
            $sw = array_merge($defaults, $s);
            $sw['id'] = str_replace(' ', '_', (string) (($s['id'] ?? '') ?: (($s['name'] ?? '') ?: $s['host'])));
            $out[] = $sw;
        }
        return $out;
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
        $user = $this->env("ARUBA_USER_{$suffix}", $env) ?: ($this->env('ARUBA_USER', $env) ?: ($sw['username'] ?? ''));
        $pwd = $this->env("ARUBA_PASSWORD_{$suffix}", $env) ?: ($this->env('ARUBA_PASSWORD', $env) ?: ($sw['password'] ?? ''));
        if (!$pwd) {
            if (!empty($sw['password_file']) && !empty($sw['key_file'])) {
                $pwd = SecurePassword::read($this->path($sw['password_file']), $this->path($sw['key_file']));
            } else {
                throw new \RuntimeException('Mot de passe absent : renseignez ARUBA_PASSWORD dans le fichier .env '
                    . 'puis cliquez sur Actualiser');
            }
        }
        if (!$user) {
            throw new \RuntimeException('Utilisateur absent : renseignez ARUBA_USER dans le fichier .env');
        }
        return [$user, $pwd];
    }

    /** Chemin relatif au dossier de config.json. */
    public function path(string $p): string
    {
        return preg_match('#^([a-zA-Z]:)?[\\\\/]#', $p) ? $p : dirname($this->configFile) . DIRECTORY_SEPARATOR . $p;
    }
}
