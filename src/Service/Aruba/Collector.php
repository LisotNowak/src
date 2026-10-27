<?php
/**
 * Collecte des switchs.
 *
 * PHP n'a pas de threads : la collecte tourne dans un processus en ligne de commande
 * (bin/console aruba:collect) qui lance lui-même un processus par switch (`--switch <id>`), jusqu'à
 * `parallel` à la fois. L'avancement est écrit dans status.json, un verrou sur collect.lock
 * empêche deux collectes simultanées. La page web ne fait que lancer ce processus en
 * arrière-plan et lire les fichiers produits.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class Collector
{
    public function __construct(
        private Config $config,
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

    public function snapshot(): ?array
    {
        return self::loadJson($this->file('snapshot.json'), null);
    }

    public function history(): array
    {
        return self::loadJson($this->file('history.json'), []);
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

    public function status(): array
    {
        $st = self::loadJson($this->file('status.json'), []);
        $queued = ($st['state'] ?? '') === 'queued' && time() - ($st['requested_at'] ?? 0) < 30;
        // lancée depuis la page mais le processus n'a pas encore pris le verrou
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
            'collected_at' => $this->snapshot()['collected_at'] ?? null,
        ];
    }

    public function isRunning(): bool
    {
        return $this->status()['running'];
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

    /** Lance une collecte en arrière-plan. Retourne false si une collecte est déjà en cours. */
    public function launch(): bool
    {
        if ($this->isRunning()) {
            return false;
        }
        self::saveJson($this->file('status.json'), ['state' => 'queued', 'requested_at' => time(), 'progress' => []]);
        $cmd = implode(' ', array_map('escapeshellarg', [$this->phpBinary(), ...$this->collectCommand]));
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
    public function run(?callable $log = null): bool
    {
        $log ??= fn(string $m) => null;
        $lock = fopen($this->file('collect.lock'), 'c');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            $log('Collecte déjà en cours');
            return false;
        }
        set_time_limit(0);
        try {
            $switches = $this->config->switches();
            $progress = $details = [];
            foreach ($switches as $sw) {
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
            $queue = $switches;
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
            $history = $this->history();
            $snap = Inventory::build($switches, $raw, $history, $this->config->get('other_switches', []));
            self::saveJson($this->file('snapshot.json'), $snap);
            self::saveJson($this->file('history.json'), (object) $history);
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

    private function client(string $id): AosCxClient|AosSwitchClient
    {
        $sw = $this->config->switch($id);
        [$user, $pwd] = $this->config->credentials($sw);
        // "os": "aos-switch" pour un ArubaOS-Switch (2930F…), AOS-CX par défaut
        return ($sw['os'] ?? 'aos-cx') === 'aos-switch'
            ? new AosSwitchClient($sw['host'], $user, $pwd, (bool) ($sw['verify_ssl'] ?? false),
                (string) ($sw['api_version'] ?? 'auto'), (int) ($sw['timeout'] ?? 15), (string) ($sw['protocol'] ?? 'https'))
            : new AosCxClient($sw['host'], $user, $pwd, (bool) ($sw['verify_ssl'] ?? false),
                (string) ($sw['api_version'] ?? 'auto'), (int) ($sw['timeout'] ?? 15));
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
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 300)];
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
            return ['error' => mb_substr((new \ReflectionClass($e))->getShortName() . ': ' . $e->getMessage(), 0, 300)];
        }
    }
}
