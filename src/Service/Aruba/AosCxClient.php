<?php
/**
 * Client minimal (lecture seule) pour l'API REST Aruba AOS-CX.
 *
 * Testé sur la logique des API v10.04 -> v10.1x. La version d'API est
 * détectée automatiquement via GET /rest sauf si elle est forcée dans la config.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class AosCxClient
{
    public const FALLBACK_VERSIONS = ['v10.18', 'v10.17', 'v10.16', 'v10.15', 'v10.14', 'v10.13',
        'v10.12', 'v10.11', 'v10.10', 'v10.09', 'v10.08', 'v10.04'];

    private string $base;
    private ?string $prefix = null;
    private \CurlHandle $ch;

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private bool $verifySsl = false,
        public string $apiVersion = 'auto',
        private int $timeout = 15,
    ) {
        $this->base = "https://{$host}";
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => '',          // cookies de session en mémoire
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout * 4,
            CURLOPT_PROXY => '',               // ne pas passer par un proxy système pour joindre les switchs
            CURLOPT_NOPROXY => '*',
        ]);
    }

    public static function normMac(?string $value): string
    {
        $h = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', (string) $value));
        if (strlen($h) !== 12) {
            return strtolower((string) $value);
        }
        return implode(':', str_split($h, 2));
    }

    /** Une référence AOS-CX peut être {"1/1/1": "/rest/..."}, ["/rest/..."], ou un objet. */
    public static function refKeys(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value) && !array_is_list($value)) {
            if (isset($value['name']) && is_string($value['name'])) {
                return [$value['name']];
            }
            if (array_key_exists('id', $value) && $value['id'] !== null && !is_array($value['id'])) {
                return [(string) $value['id']];
            }
            return array_map('strval', array_keys($value));
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $v) {
                if (is_string($v)) {
                    $parts = explode('/', rtrim($v, '/'));
                    $out[] = rawurldecode(end($parts));
                } else {
                    array_push($out, ...self::refKeys($v));
                }
            }
            return $out;
        }
        if (is_string($value)) {
            $parts = explode('/', rtrim($value, '/'));
            return [rawurldecode(end($parts))];
        }
        return [(string) $value];
    }

    // ------------------------------------------------------------ HTTP
    /** @return array{0:int,1:string} */
    private function request(string $method, string $url, ?array $form = null): array
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        if ($method === 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($form ?? []));
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        }
        $body = curl_exec($this->ch);
        if ($body === false) {
            throw new AosCxException(sprintf('Connexion à %s impossible : %s', $this->host, curl_error($this->ch)));
        }
        return [(int) curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE), (string) $body];
    }

    private static function withParams(string $url, array $params): string
    {
        return $params ? $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params) : $url;
    }

    // ------------------------------------------------------------ session
    private function detectVersion(): string
    {
        if ($this->apiVersion && $this->apiVersion !== 'auto') {
            return $this->apiVersion;
        }
        try {
            [, $body] = $this->request('GET', "{$this->base}/rest");
            $data = json_decode($body, true);
            if (is_array($data)) {
                if (is_array($data['latest'] ?? null) && !empty($data['latest']['version'])) {
                    return $data['latest']['version'];
                }
                $versions = array_values(array_filter(array_keys($data),
                    fn($k) => is_string($k) && preg_match('/^v\d+\.\d+$/', $k)));
                if ($versions) {
                    usort($versions, fn($a, $b) => version_compare(substr($a, 1), substr($b, 1)));
                    return end($versions);
                }
            }
        } catch (AosCxException) {
        }
        return '';
    }

    private function tryLogin(string $ver): array
    {
        $prefix = "{$this->base}/rest/{$ver}";
        $creds = ['username' => $this->username, 'password' => $this->password];
        $r = $this->request('POST', "{$prefix}/login", $creds);
        if ($r[0] !== 200) {  // certains firmwares n'acceptent que les paramètres d'URL
            $r = $this->request('POST', self::withParams("{$prefix}/login", $creds), []);
        }
        return $r;
    }

    public function login(): void
    {
        $ver = $this->detectVersion();
        $last = null;
        foreach ($ver ? [$ver] : self::FALLBACK_VERSIONS as $v) {
            $r = $this->tryLogin($v);
            if ($r[0] === 200) {
                $this->apiVersion = $v;
                $this->prefix = "{$this->base}/rest/{$v}";
                return;
            }
            $last = $r;
            if (in_array($r[0], [401, 403], true)) {  // mauvais identifiants : inutile d'essayer d'autres versions
                break;
            }
        }
        throw new AosCxException(sprintf('Échec login (%d) : %s', $last[0], mb_substr($last[1], 0, 200)));
    }

    public function logout(): void
    {
        if ($this->prefix) {
            try {
                $this->request('POST', "{$this->prefix}/logout", []);
            } catch (AosCxException) {
            }
        }
    }

    /** GET avec filtre d'attributs ; si le firmware refuse un attribut, on réessaie sans filtre. */
    public function getAttrs(string $path, string $attributes, array $params = []): mixed
    {
        try {
            return $this->get($path, ['attributes' => $attributes] + $params);
        } catch (AosCxException $e) {
            if (str_contains($e->getMessage(), '-> 400')) {
                return $this->get($path, $params);
            }
            throw $e;
        }
    }

    public function get(string $path, array $params = []): mixed
    {
        $url = str_starts_with($path, 'http') ? $path
            : (str_starts_with($path, '/rest/') ? $this->base . $path : $this->prefix . $path);
        [$code, $body] = $this->request('GET', self::withParams($url, $params));
        if ($code === 404) {
            return null;
        }
        if ($code !== 200) {
            throw new AosCxException("GET {$path} -> {$code} " . mb_substr($body, 0, 200));
        }
        return json_decode($body, true);
    }

    private static function items(mixed $data): array
    {
        return is_array($data) ? array_values($data) : [];
    }

    // ------------------------------------------------------------ données
    public function system(): array
    {
        $d = $this->getAttrs('/system', 'hostname,platform_name,software_version,system_mac') ?: [];
        return [
            'hostname' => ($d['hostname'] ?? '') ?: $this->host,
            'platform' => $d['platform_name'] ?? '' ?: '',
            'version' => $d['software_version'] ?? '' ?: '',
            'system_mac' => self::normMac($d['system_mac'] ?? ''),
        ];
    }

    public function interfaces(): array
    {
        $attrs = 'name,type,description,admin_state,link_state,link_speed,duplex,'
            . 'vlan_mode,vlan_tag,vlan_trunks,interfaces,lldp_neighbors';
        $data = $this->getAttrs('/system/interfaces', $attrs, ['depth' => 2]) ?: [];
        $out = [];
        foreach (self::items($data) as $it) {
            if (!is_array($it) || empty($it['name'])) {
                continue;
            }
            $name = (string) $it['name'];
            $itype = (string) ($it['type'] ?? '');
            if (in_array($itype, ['loopback', 'vlan', 'tunnel'], true)
                || preg_match('/^(loopback|vlan|tunnel)/', $name)) {
                continue;
            }
            $trunks = self::refKeys($it['vlan_trunks'] ?? null);
            usort($trunks, fn($a, $b) => (ctype_digit($a) ? (int) $a : 0) <=> (ctype_digit($b) ? (int) $b : 0));
            $tag = self::refKeys($it['vlan_tag'] ?? null);
            $out[$name] = [
                'name' => $name,
                'type' => $itype ?: (str_starts_with($name, 'lag') ? 'lag' : 'system'),
                'description' => $it['description'] ?? '' ?: '',
                'admin' => $it['admin_state'] ?? '' ?: '',
                'link' => $it['link_state'] ?? '' ?: '',
                'speed' => $it['link_speed'] ?? 0 ?: 0,
                'duplex' => $it['duplex'] ?? '' ?: '',
                'vlan_mode' => $it['vlan_mode'] ?? '' ?: '',
                'native_vlan' => $tag[0] ?? '',
                'trunks' => $trunks,
                'members' => str_starts_with($name, 'lag') ? self::refKeys($it['interfaces'] ?? null) : [],
                '_lldp_refs' => array_key_exists('lldp_neighbors', $it) ? $it['lldp_neighbors'] : '__absent__',
            ];
        }
        return $out;
    }

    public function vlans(): array
    {
        $data = $this->getAttrs('/system/vlans', 'id,name', ['depth' => 2]) ?: [];
        $out = [];
        foreach (self::items($data) as $v) {
            if (is_array($v) && isset($v['id'])) {
                $out[(string) $v['id']] = $v['name'] ?? '' ?: '';
            }
        }
        return $out;
    }

    public function macs(array $vlanIds): array
    {
        $out = [];
        foreach ($vlanIds as $vid) {
            try {
                $data = $this->get("/system/vlans/{$vid}/macs", ['depth' => 2]) ?: [];
            } catch (AosCxException) {
                continue;
            }
            foreach (self::items($data) as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $ports = self::refKeys($m['port'] ?? null);
                $out[] = [
                    'mac' => self::normMac($m['mac_addr'] ?? ''),
                    'vlan' => (string) $vid,
                    'port' => $ports[0] ?? '',
                    'type' => $m['from'] ?? '' ?: '',
                ];
            }
        }
        return $out;
    }

    /** Retourne {port_local: [voisins]}. */
    public function lldp(array $interfaces): array
    {
        $result = [];
        foreach ($interfaces as $name => $itf) {
            $name = (string) $name;
            $refs = $itf['_lldp_refs'] ?? null;
            $urls = [];
            $objs = [];
            if (is_string($refs) && $refs !== '__absent__') {
                // firmwares récents (10.15+) : URL de la collection des voisins
                if ($itf['link'] !== 'up' || $itf['type'] === 'lag') {
                    continue;
                }
                try {
                    $objs = self::items($this->get($refs, ['depth' => 2]));
                } catch (AosCxException) {
                    continue;
                }
            } elseif ($refs === '__absent__') {
                // attribut non exposé : on interroge les ports UP physiquement
                if ($itf['link'] !== 'up' || $itf['type'] === 'lag') {
                    continue;
                }
                try {
                    $objs = self::items($this->get('/system/interfaces/' . rawurlencode($name) . '/lldp_neighbors',
                        ['depth' => 2]));
                } catch (AosCxException) {
                    continue;
                }
            } elseif (is_array($refs) && $refs) {
                $vals = array_values($refs);
                if (!array_is_list($refs) && is_array($vals[0])) {
                    $objs = $vals;
                } else {
                    $urls = array_filter($vals, 'is_string');
                    $objs = array_values(array_filter($vals, 'is_array'));
                }
                foreach ($urls as $u) {
                    try {
                        $o = $this->get($u);
                        if ($o) {
                            $objs[] = $o;
                        }
                    } catch (AosCxException) {
                    }
                }
            } else {
                continue;
            }
            foreach ($objs as $o) {
                if (!is_array($o)) {
                    continue;
                }
                $ni = $o['neighbor_info'] ?? [] ?: [];
                $mgmt = $ni['mgmt_ip_list'] ?? null ?: ($o['mgmt_ip_list'] ?? '') ?: '';
                if (is_array($mgmt)) {
                    $mgmt = implode(',', $mgmt);
                }
                $caps = $ni['chassis_capability_enabled'] ?? null ?: ($ni['chassis_capability_available'] ?? '') ?: '';
                if (is_array($caps)) {
                    $caps = implode(', ', $caps);
                }
                $cid = (string) ($o['chassis_id'] ?? '');
                $result[$name][] = [
                    'chassis_id' => preg_match('/^[0-9a-fA-F:.\-]{12,17}$/', $cid) ? self::normMac($cid) : $cid,
                    'port_id' => $o['port_id'] ?? '' ?: '',
                    'name' => $ni['chassis_name'] ?? '' ?: '',
                    'description' => ($ni['chassis_description'] ?? null) ?: ($ni['system_description'] ?? '') ?: '',
                    'port_description' => $ni['port_description'] ?? '' ?: '',
                    'mgmt_ip' => $mgmt ? trim(explode(',', $mgmt)[0]) : '',
                    'capabilities' => $caps,
                ];
            }
        }
        return $result;
    }

    /** mac -> ip (si le switch route). */
    public function arp(): array
    {
        $out = [];
        try {
            $vrfs = $this->get('/system/vrfs') ?: [];
        } catch (AosCxException) {
            return $out;
        }
        foreach (is_array($vrfs) && !array_is_list($vrfs) ? array_keys($vrfs) : [] as $vrf) {
            try {
                $data = $this->getAttrs('/system/vrfs/' . rawurlencode((string) $vrf) . '/neighbors',
                    'mac,ip_address', ['depth' => 2]) ?: [];
            } catch (AosCxException) {
                continue;
            }
            foreach (self::items($data) as $n) {
                if (is_array($n) && !empty($n['mac']) && !empty($n['ip_address'])) {
                    $mac = self::normMac($n['mac']);
                    if (str_contains($n['ip_address'], ':')) {  // on privilégie IPv4
                        $out[$mac] ??= $n['ip_address'];
                    } else {
                        $out[$mac] = $n['ip_address'];
                    }
                }
            }
        }
        return $out;
    }

    public function collect(): array
    {
        $system = $this->system();
        $itfs = $this->interfaces();
        $vlans = $this->vlans();
        $macs = $this->macs(array_keys($vlans));
        $lldp = $this->lldp($itfs);
        $arp = $this->arp();
        foreach ($itfs as &$i) {
            unset($i['_lldp_refs']);
        }
        unset($i);
        return ['system' => $system, 'interfaces' => $itfs, 'vlans' => $vlans,
            'macs' => $macs, 'lldp' => $lldp, 'arp' => $arp, 'api_version' => $this->apiVersion];
    }
}
