<?php
/**
 * Collecte des switchs.
 *
 * PHP n'a pas de threads : la collecte tourne dans un processus en ligne de commande
 * (bin/console aruba:collect) qui lance lui-même un processus par switch (`--switch <id>`), jusqu'à
 * `parallel` à la fois. L'avancement est écrit dans status.json, un verrou sur collect.lock
 * empêche deux collectes simultanées. Le résultat (photo du réseau, appareils vus) est enregistré
 * en base à la fin par ce seul processus (App\Service\Aruba\Store). La page web ne fait que lancer
 * ce processus en arrière-plan, suivre status.json et lire la base.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class Collector
{
    /** Identifiant réservé : GLPI est interrogé comme un équipement de plus, dans son propre processus */
    public const GLPI = 'glpi';

    /** Ligne ajoutée à l'avancement si l'enregistrement en base échoue */
    private const DB = 'base';

    public function __construct(
        private Config $config,
        private Store $store,
        private string $dataDir,
        /** Arguments donnés à php pour lancer la collecte : ['bin/console', 'aruba:collect'] */
        private array $collectCommand,
    ) {
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
        }
    }

    private function file(string $name): string
    {
        return $this->dataDir . DIRECTORY_SEPARATOR . $name;
    }

    // ------------------------------------------------------------ fichiers JSON
    public static function loadJson(string $path, mixed $default): mixed
    {
        $text = @file_get_contents($path);
        if ($text === false) {
            return $default;
        }
        $data = json_decode($text, true);
        return $data ?? $default;
    }

    public static function saveJson(string $path, mixed $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $tmp = $path . '.' . getmypid() . '.tmp';
        file_put_contents($tmp, $json);
        // sous Windows, le remplacement échoue si un lecteur a le fichier ouvert à cet instant
        for ($i = 0; $i < 20; $i++) {
            if (@rename($tmp, $path)) {
                return;
            }
            usleep(50_000);
        }
        file_put_contents($path, $json, LOCK_EX);
        @unlink($tmp);
    }

    /** Photo du réseau de la dernière collecte (en base), null si aucune collecte. */
    public function snapshot(): ?array
    {
        return $this->store->lastSnapshot();
    }

    // ------------------------------------------------------------ état
    /** Le verrou est-il tenu par un processus de collecte ? */
    private function lockHeld(): bool
    {
        $h = @fopen($this->file('collect.lock'), 'c');
        if (!$h) {
            return false;
        }
        $free = flock($h, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($h, LOCK_UN);
        }
        fclose($h);
        return !$free;
    }

    /** Lancée depuis la page (ou le planificateur) mais le processus n'a pas encore pris le verrou ? */
    private static function queued(array $st): bool
    {
        return ($st['state'] ?? '') === 'queued' && time() - ($st['requested_at'] ?? 0) < 30;
    }

    public function status(): array
    {
        $st = self::loadJson($this->file('status.json'), []);
        $queued = self::queued($st);
        $running = $this->lockHeld() || $queued;
        $show = $running || ($st['state'] ?? '') === 'done';
        $details = $show ? ($st['details'] ?? []) : [];
        foreach ($details as &$d) {
            if (isset($d['start']) && !isset($d['duration'])) {
                $d['elapsed'] = time() - $d['start'];
            }
            unset($d['start']);
        }
        unset($d);
        return [
            'running' => $running,
            // demarrage → collecte (switchs interrogés en parallèle) → analyse (construction de l'inventaire) → fin
            'phase' => $queued ? 'demarrage' : ($running ? ($st['phase'] ?? 'collecte') : 'fin'),
            'elapsed' => $show && isset($st['started_at']) ? (($st['finished_at'] ?? time()) - $st['started_at']) : null,
            'progress' => (object) ($show ? ($st['progress'] ?? []) : []),
            'details' => (object) $details,
            'collected_at' => $this->store->lastCollectedAt(),
            'auto' => $this->autoState(),
        ];
    }

    public function isRunning(): bool
    {
        return $this->lockHeld() || self::queued(self::loadJson($this->file('status.json'), []));
    }

    // ------------------------------------------------------------ collecte automatique
    /**
     * Le planificateur (bin/console aruba:auto, voir ArubaAutoCommand) passe toutes les 30 s (--boucle) ou
     * toutes les minutes (tâche planifiée) et lance une collecte quand l'intervalle est écoulé depuis la
     * précédente, qu'elle ait été lancée à la main ou non. Il note son passage dans auto.json : l'écran
     * Configuration signale ainsi un planificateur arrêté.
     */
    public const AUTO = 'automatique';

    /** Lancement avec un peu d'avance : le planificateur ne passe qu'à intervalle régulier */
    private const AUTO_TOLERANCE = 30;

    /** Au-delà, le planificateur est considéré comme arrêté */
    private const AUTO_SILENCE = 180;

    /** Heure locale (PHP tourne en UTC) pour les journaux du planificateur */
    public static function heure(?int $ts = null): string
    {
        return (new \DateTimeImmutable('@' . ($ts ?? time())))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i:s');
    }

    /** @return array{enabled: bool, interval: int} intervalle en minutes */
    public function autoSettings(): array
    {
        return ['enabled' => (bool) $this->config->get('auto_collect', true),
            'interval' => max(5, (int) $this->config->get('auto_interval', 15))];
    }

    /** Début de la dernière collecte ou tentative automatique (timestamp), null si aucune */
    private function autoLast(array $state): ?int
    {
        $last = max((int) $this->store->lastDebut(), (int) ($state['last_launch'] ?? 0));
        return $last ?: null;
    }

    /** État affiché : réglage, heure de la prochaine collecte, planificateur actif ou non. */
    public function autoState(): array
    {
        $cfg = $this->autoSettings();
        $state = self::loadJson($this->file('auto.json'), []);
        $checked = $state['checked_at'] ?? null;
        $last = $cfg['enabled'] ? $this->autoLast($state) : null;
        $next = $last === null ? time() : max(time(), $last + $cfg['interval'] * 60);
        return $cfg + [
            'next_at' => $cfg['enabled'] ? gmdate('Y-m-d\TH:i:s', $next) . '+00:00' : null,
            'scheduler' => $checked !== null && time() - $checked < self::AUTO_SILENCE,
            'checked_at' => $checked !== null ? gmdate('Y-m-d\TH:i:s', $checked) . '+00:00' : null,
            'mode' => $state['mode'] ?? null,
        ];
    }

    /**
     * Un passage du planificateur : lance la collecte si elle est due.
     * $detach : collecte dans un processus à part (mode boucle), sinon dans ce processus (tâche planifiée).
     * Retourne ce qui a été fait, pour le journal.
     */
    public function autoTick(bool $detach): string
    {
        $this->config->reload();
        $cfg = $this->autoSettings();
        $state = self::loadJson($this->file('auto.json'), []);
        $state = ['checked_at' => time(), 'mode' => $detach ? 'boucle' : 'tache', 'pid' => getmypid()] + $state;
        self::saveJson($this->file('auto.json'), $state);
        if (!$cfg['enabled']) {
            return 'désactivée';
        }
        $last = $this->autoLast($state);
        if ($last !== null && time() < $last + $cfg['interval'] * 60 - self::AUTO_TOLERANCE) {
            return 'prochaine à ' . self::heure($last + $cfg['interval'] * 60);
        }
        if ($this->isRunning()) {
            return 'collecte déjà en cours';
        }
        // noté avant le lancement : une collecte qui échoue avant d'arriver en base n'est pas relancée en boucle
        $state['last_launch'] = time();
        self::saveJson($this->file('auto.json'), $state);
        if ($detach) {
            return $this->launch(self::AUTO) ? 'collecte lancée' : 'collecte déjà en cours';
        }
        return $this->run(null, self::AUTO) ? 'collecte terminée' : 'collecte déjà en cours';
    }

    // ------------------------------------------------------------ lancement depuis le web
    private function phpBinary(): string
    {
        $bin = $this->config->get('php_binary');
        if ($bin) {
            return $bin;
        }
        // sous php-cgi (serveur Symfony CLI) / php-fpm / Apache, PHP_BINARY n'est pas l'exécutable
        // en ligne de commande : on cherche php à côté (cas de php-cgi.exe sous Windows), sinon dans le PATH
        if (in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
            return PHP_BINARY;
        }
        $cli = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');
        return PHP_BINARY !== '' && is_file($cli) ? $cli : 'php';
    }

    /**
     * Lance une collecte en arrière-plan. Retourne false si une collecte est déjà en cours.
     * $par : identifiant de l'utilisateur, enregistré avec la collecte.
     */
    public function launch(?string $par = null): bool
    {
        if ($this->isRunning()) {
            return false;
        }
        self::saveJson($this->file('status.json'), ['state' => 'queued', 'requested_at' => time(), 'progress' => []]);
        $args = [$this->phpBinary(), ...$this->collectCommand];
        if ($par !== null && $par !== '') {
            array_push($args, '--par', $par);
        }
        $cmd = implode(' ', array_map('escapeshellarg', $args));
        if (PHP_OS_FAMILY === 'Windows') {
            // processus détaché : sans quoi il hériterait de la connexion HTTP et la bloquerait
            pclose(popen("start \"\" /B {$cmd} >NUL 2>&1", 'r'));
        } else {
            exec("nohup {$cmd} > /dev/null 2>&1 &");
        }
        return true;
    }

    // ------------------------------------------------------------ collecte (ligne de commande)
    /** Collecte complète. Retourne false si une collecte est déjà en cours. */
    public function run(?callable $log = null, ?string $par = null): bool
    {
        $log ??= fn(string $m) => null;
        $lock = fopen($this->file('collect.lock'), 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            $log('Collecte déjà en cours');
            return false;
        }
        set_time_limit(0);
        try {
            // les équipements désactivés restent dans la configuration mais ne sont pas interrogés
            $switches = array_values(array_filter($this->config->switches(), fn(array $sw) => $sw['actif'] ?? true));
            $jobs = $switches;
            $glpi = $this->config->glpi();
            if ($glpi && ($glpi['enabled'] ?? true)) {
                $jobs[] = ['id' => self::GLPI, 'name' => 'GLPI', 'host' => $glpi['url'], 'timeout' => 30];
            }
            $progress = $details = [];
            foreach ($jobs as $sw) {
                $progress[$sw['id']] = 'en attente';
                $details[$sw['id']] = ['name' => ($sw['name'] ?? '') ?: $sw['host']];
            }
            $startedAt = time();
            $saveStatus = function (string $state, string $phase = 'collecte') use (&$progress, &$details, $startedAt) {
                self::saveJson($this->file('status.json'), ['state' => $state, 'phase' => $phase,
                    'requested_at' => time(), 'started_at' => $startedAt,
                    'finished_at' => $state === 'done' ? time() : null, 'progress' => $progress, 'details' => $details]);
            };
            $saveStatus('running');

            $raw = [];
            $queue = $jobs;
            $workers = [];
            $parallel = max(1, (int) $this->config->get('parallel', 8));
            while ($queue || $workers) {
                while ($queue && count($workers) < $parallel) {
                    $sw = array_shift($queue);
                    $workers[$sw['id']] = $this->startWorker($sw);
                    $progress[$sw['id']] = 'en cours';
                    $details[$sw['id']]['start'] = time();
                    $saveStatus('running');
                }
                usleep(200_000);
                foreach ($workers as $id => $w) {
                    $st = proc_get_status($w['proc']);
                    $timeout = time() - $w['start'] > $w['max'];
                    if ($st['running'] && !$timeout) {
                        continue;
                    }
                    if ($timeout) {
                        proc_terminate($w['proc']);
                    }
                    proc_close($w['proc']);
                    $data = json_decode((string) @file_get_contents($w['out']), true);
                    if (!is_array($data)) {
                        $err = trim((string) @file_get_contents($w['err']));
                        $data = ['error' => $timeout ? 'Délai de collecte dépassé'
                            : mb_substr('Processus de collecte en échec : ' . ($err ?: 'aucune sortie'), 0, 300)];
                    }
                    @unlink($w['out']);
                    @unlink($w['err']);
                    $raw[$id] = $data;
                    $progress[$id] = isset($data['error']) ? 'erreur' : 'ok';
                    $details[$id]['duration'] = $data['duration'] ?? time() - $w['start'];
                    if (isset($data['error'])) {
                        $details[$id]['error'] = $data['error'];
                    }
                    $log(sprintf('%-30s %s', $id, $data['error'] ?? "ok ({$data['duration']} s)"));
                    unset($workers[$id]);
                    $saveStatus('running');
                }
            }

            $saveStatus('running', 'analyse');
            $oui = $this->config->path('oui.csv');
            if (is_file($oui)) {
                Inventory::loadOui($oui);
            }
            try {
                $previous = $history = $this->store->loadHistory();
                $snap = Inventory::build($switches, $raw, $history, $this->config->get('other_switches', []),
                    isset($raw[self::GLPI]) ? $raw[self::GLPI] : null);
                $results = [];
                foreach ($progress as $id => $state) {
                    $results[$id] = ['statut' => $state === 'ok' ? 'ok' : 'erreur',
                        'duree' => $details[$id]['duration'] ?? null, 'erreur' => $details[$id]['error'] ?? null];
                }
                $this->store->record($snap, $results, $history, $previous, new \DateTimeImmutable('@' . $startedAt),
                    $par, (int) $this->config->get('retention_days', 0), (int) $this->config->get('retention_presence_days', 0),
                    (int) $this->config->get('retention_full_days', 7));
            } catch (\Throwable $e) {
                // affiché dans le suivi de la collecte comme un équipement en erreur
                $progress[self::DB] = 'erreur';
                $details[self::DB] = ['name' => 'Enregistrement en base', 'error' => mb_substr($e->getMessage(), 0, 300)];
                $saveStatus('done');
                throw $e;
            }
            $saveStatus('done');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return true;
    }

    private function startWorker(array $sw): array
    {
        $base = $this->file('worker-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $sw['id']));
        $proc = proc_open([PHP_BINARY, ...$this->collectCommand, '--switch', $sw['id']],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
                1 => ['file', "{$base}.out", 'w'], 2 => ['file', "{$base}.err", 'w']],
            $pipes, null, null, ['bypass_shell' => true]);
        return ['proc' => $proc, 'out' => "{$base}.out", 'err' => "{$base}.err", 'start' => time(),
            'max' => max(120, (int) ($sw['timeout'] ?? 15) * 40)];
    }

    private function client(string $id): AosCxClient|AosSwitchClient|InstantClient|GlpiClient
    {
        if ($id === self::GLPI) {
            $g = $this->config->glpi() ?? throw new \RuntimeException('GLPI n\'est pas configuré');
            $secret = $this->config->secret('glpi-client');
            $pwd = $this->config->secret('glpi');
            if ($secret === '' || $pwd === '') {
                throw new \RuntimeException('GLPI : secret du client OAuth ou mot de passe absent (Configuration)');
            }
            return new GlpiClient($g['url'], (string) ($g['client_id'] ?? ''), $secret, (string) ($g['username'] ?? ''), $pwd);
        }
        $sw = $this->config->switch($id);
        [$user, $pwd] = $this->config->credentials($sw);
        // "os" : "aos-switch" pour un ArubaOS-Switch (2930F…), "instant" pour un cluster de bornes Wi-Fi,
        // AOS-CX par défaut
        return match ($sw['os'] ?? 'aos-cx') {
            'aos-switch' => new AosSwitchClient($sw['host'], $user, $pwd, (bool) ($sw['verify_ssl'] ?? false),
                (string) ($sw['api_version'] ?? 'auto'), (int) ($sw['timeout'] ?? 15), (string) ($sw['protocol'] ?? 'https')),
            'instant' => new InstantClient($sw['host'], $user, $pwd, (bool) ($sw['verify_ssl'] ?? false),
                (int) ($sw['timeout'] ?? 15)),
            default => new AosCxClient($sw['host'], $user, $pwd, (bool) ($sw['verify_ssl'] ?? false),
                (string) ($sw['api_version'] ?? 'auto'), (int) ($sw['timeout'] ?? 15)),
        };
    }

    /** Message d'erreur lisible (les erreurs curl et HTTP sont en anglais ou techniques). */
    public static function errorMessage(\Throwable $e): string
    {
        $m = $e->getMessage();
        $m = (string) preg_replace([
            '/\s*:?\s*(Connection|Operation) timed out after \d+ milliseconds.*/i',
            '/\s*:?\s*Failed to connect.*Timed out.*/i',
            '/\s*:?\s*(Failed to connect|Couldn\'t connect).*/i',
            '/\s*:?\s*Could not resolve host.*/i',
            '/^Échec login \((401|403)\).*/s',
            '/\s*:?\s*(OpenSSL|SSL|schannel).*/i',
        ], [
            ' : pas de réponse (délai dépassé)',
            ' : pas de réponse (délai dépassé)',
            ' : connexion refusée',
            ' : nom introuvable',
            'Identifiant ou mot de passe refusé',
            ' : erreur de connexion sécurisée (HTTPS)',
        ], $m);
        return mb_substr($m, 0, 300);
    }

    /** Test de connexion (écran Configuration) : login, identification, logout. */
    public function testSwitch(string $id): array
    {
        $t0 = microtime(true);
        try {
            $c = $this->client($id);
            $c->login();
            try {
                $system = $c->system();
            } finally {
                $c->logout();
            }
            return ['ok' => true, 'system' => $system, 'api_version' => $c->apiVersion,
                'duration' => round(microtime(true) - $t0, 1)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::errorMessage($e)];
        }
    }

    /** Interroge un seul switch (exécuté dans un processus dédié). */
    public function collectSwitch(string $id): array
    {
        $t0 = microtime(true);
        try {
            $c = $this->client($id);
            $c->login();
            try {
                $data = $c->collect();
            } finally {
                $c->logout();
            }
            $data['collected_at'] = gmdate('Y-m-d\TH:i:s') . '+00:00';
            $data['duration'] = round(microtime(true) - $t0, 1);
            return $data;
        } catch (\Throwable $e) {
            return ['error' => self::errorMessage($e)];
        }
    }
}
