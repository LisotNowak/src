<?php
/**
 * Client minimal (lecture seule) pour l'API REST d'un cluster Aruba Instant (bornes Wi-Fi AP-5xx…).
 *
 * On interroge le contrôleur virtuel (port 4343, « allow-rest-api » activé) : il répond pour toutes
 * les bornes du cluster. L'API ne fait qu'exécuter des commandes d'affichage (GET /rest/show-cmd),
 * dont la sortie texte en colonnes est découpée ici.
 * Testé sur Instant 8.12 et 8.13 (AP-503, AP-565).
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class InstantClient
{
    private string $base;
    private ?string $sid = null;
    public string $apiVersion = 'REST Instant';

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private bool $verifySsl = false,
        private int $timeout = 15,
        private int $port = 4343,
    ) {
        $this->base = "https://{$host}:{$port}/rest";
    }

    // ------------------------------------------------------------ HTTP
    /** @return array{0:int,1:string} */
    private function request(string $method, string $url, ?array $json = null): array
    {
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout * 4,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $json === null ? null : json_encode($json),
            // la borne ferme la connexion TLS sans la clore proprement, ce qu'OpenSSL 3 signale comme une
            // erreur après réception de la réponse : on garde donc ce qui a été reçu
            CURLOPT_WRITEFUNCTION => function ($ch, string $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($ok === false && ($code === 0 || $body === '')) {
            throw new AosCxException(sprintf('Connexion à %s impossible : %s', $this->host, curl_error($ch)));
        }
        return [$code, $body];
    }

    /** Sortie texte d'une commande d'affichage (« show … »). */
    public function show(string $cmd): string
    {
        if (!str_starts_with($cmd, 'show ')) {
            throw new \InvalidArgumentException('Seules les commandes « show » sont autorisées');
        }
        [$code, $body] = $this->request('GET', "{$this->base}/show-cmd?" . http_build_query(
            ['iap_ip_addr' => $this->host, 'cmd' => $cmd, 'sid' => $this->sid]));
        if ($code !== 200) {
            throw new AosCxException("{$cmd} -> {$code} " . mb_substr(strip_tags($body), 0, 200));
        }
        if (!mb_check_encoding($body, 'UTF-8')) {
            // noms d'appareils parfois envoyés en Latin-1 (« OrdiH\xE9lie ») : ces octets sont convertis,
            // l'UTF-8 valide est conservé
            $body = (string) preg_replace_callback('/[\xC2-\xF4][\x80-\xBF]+|[\x80-\xFF]/',
                fn($m) => mb_check_encoding($m[0], 'UTF-8') ? $m[0] : mb_convert_encoding($m[0], 'UTF-8', 'Windows-1252'), $body);
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new AosCxException("{$cmd} : réponse illisible (" . json_last_error_msg() . ')');
        }
        return (string) ($data['Command output'] ?? '');
    }

    /**
     * Tableau en colonnes (en-tête, ligne de tirets, lignes de données) → liste de lignes {en-tête: valeur}.
     * Les colonnes sont délimitées par les groupes de tirets.
     */
    public static function table(string $text): array
    {
        $lines = preg_split('/\R/', $text);
        foreach ($lines as $i => $line) {
            if ($i === 0 || !preg_match('/^-+( +-+){2,} *$/', $line)) {
                continue;
            }
            preg_match_all('/-+/', $line, $m, PREG_OFFSET_CAPTURE);
            $starts = array_column($m[0], 1);
            $cut = function (string $l) use ($starts): array {
                $out = [];
                foreach ($starts as $k => $s) {
                    $len = isset($starts[$k + 1]) ? $starts[$k + 1] - $s : null;
                    $out[] = trim((string) ($len === null ? substr($l, $s) : substr($l, $s, $len)));
                }
                return $out;
            };
            $head = $cut($lines[$i - 1]);
            $rows = [];
            // fin du tableau : ligne vide ou ligne de total (« Number of Clients   :10 »)
            for ($j = $i + 1; $j < count($lines) && trim($lines[$j]) !== '' && !preg_match('/^\S[^:]*\s:/', $lines[$j]); $j++) {
                $rows[] = array_combine($head, $cut($lines[$j]));
            }
            return $rows;
        }
        return [];
    }

    /** « Clé :valeur » par ligne (show summary). */
    private static function keyValues(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $line) {
            if (preg_match('/^([^:]+?)\s*:(.*)$/', $line, $m)) {
                $out[trim($m[1])] ??= trim($m[2]);
            }
        }
        return $out;
    }

    // ------------------------------------------------------------ session
    public function login(): void
    {
        [$code, $body] = $this->request('POST', "{$this->base}/login", ['user' => $this->username, 'passwd' => $this->password]);
        $data = json_decode($body, true);
        if ($code === 503) {
            throw new AosCxException("API REST désactivée sur {$this->host} (commande « allow-rest-api » sur le contrôleur virtuel)");
        }
        if ($code !== 200 || empty($data['sid'])) {
            throw new AosCxException(sprintf('Échec login (%d) : %s', $code,
                mb_substr((string) ($data['message'] ?? strip_tags($body)), 0, 200)));
        }
        $this->sid = $data['sid'];
    }

    public function logout(): void
    {
        if ($this->sid) {
            try {
                $this->request('GET', "{$this->base}/logout?" . http_build_query(['sid' => $this->sid]));
            } catch (AosCxException) {
            }
            $this->sid = null;
        }
    }

    // ------------------------------------------------------------ données
    private ?array $summary = null;

    private function summary(): array
    {
        return $this->summary ??= self::keyValues($this->show('show summary'));
    }

    public function version(): string
    {
        return preg_match('/Version\s+([\d.]+)/', $this->show('show version'), $m) ? $m[1] : '';
    }

    public function aps(): array
    {
        $out = [];
        foreach (self::table($this->show('show aps')) as $r) {
            $ip = (string) ($r['IP Address'] ?? '');
            $out[] = [
                'name' => $r['Name'] ?? '',
                'ip' => rtrim($ip, '*'),
                'conductor' => str_ends_with($ip, '*'),   // borne qui porte le contrôleur virtuel
                'model' => preg_match('/^(\d+)/', (string) ($r['Type'] ?? ''), $m) ? 'AP-' . $m[1] : ($r['Type'] ?? ''),
                'serial' => $r['Serial #'] ?? '',
                'mode' => $r['Mode'] ?? '',
                'clients' => (int) ($r['Clients'] ?? 0),
                'channels' => array_values(array_filter(
                    [$r['radio0 Channel'] ?? '', $r['radio1 Channel'] ?? '', $r['radio2 Channel'] ?? ''],
                    fn($c) => $c !== '' && $c !== '-')),
                'uptime' => $r['Age'] ?? '',
                'uplink' => $r['Uplink Port'] ?? '',
            ];
        }
        return $out;
    }

    public function clients(): array
    {
        $out = [];
        foreach (self::table($this->show('show clients')) as $r) {
            $channel = (string) ($r['Channel'] ?? '');
            $type = (string) ($r['Type'] ?? '');
            $out[] = [
                'name' => $r['Name'] ?? '',
                'ip' => $r['IP Address'] ?? '',
                'mac' => AosCxClient::normMac($r['MAC Address'] ?? ''),
                'os' => ($r['OS'] ?? '') === 'NOFP' ? '' : ($r['OS'] ?? ''),
                'essid' => $r['ESSID'] ?? '',
                'ap' => $r['Access Point'] ?? '',
                'channel' => $channel,
                'band' => (int) $channel > 14 ? '5 GHz' : ((int) $channel > 0 ? '2,4 GHz' : ''),
                'standard' => str_contains($type, 'HE') ? 'Wi-Fi 6' : (str_contains($type, 'AC') || str_contains($type, 'VHT') ? 'Wi-Fi 5'
                    : (str_contains($type, 'HT') || str_contains($type, 'N') ? 'Wi-Fi 4' : $type)),
                'role' => $r['Role'] ?? '',
                'signal' => (int) ($r['Signal'] ?? 0),
                'signal_quality' => preg_match('/\((\w+)\)/', (string) ($r['Signal'] ?? ''), $m) ? $m[1] : '',
                'speed' => (int) ($r['Speed (mbps)'] ?? 0),
            ];
        }
        return $out;
    }

    public function system(): array
    {
        $s = $this->summary();
        return [
            'hostname' => ($s['Name'] ?? '') ?: $this->host,
            'platform' => 'Aruba Instant (' . count($this->aps()) . ' bornes)',
            'version' => $this->version(),
            'system_mac' => '',
        ];
    }

    public function collect(): array
    {
        $s = $this->summary();
        $aps = $this->aps();
        $version = $this->version();
        return [
            'system' => ['hostname' => ($s['Name'] ?? '') ?: $this->host, 'platform' => 'Aruba Instant (' . count($aps) . ' bornes)',
                'version' => $version, 'system_mac' => ''],
            'wifi' => [
                'name' => $s['Name'] ?? '',
                'location' => $s['System Location'] ?? '',
                'version' => $version,
                'aps' => $aps,
                'clients' => $this->clients(),
            ],
            'api_version' => $this->apiVersion,
        ];
    }
}
