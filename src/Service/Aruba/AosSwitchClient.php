<?php
/**
 * Client minimal (lecture seule) pour l'API REST ArubaOS-Switch (ex-ProCurve : 2530, 2930F, 3810…).
 *
 * Mêmes méthodes et même format de sortie que AosCxClient::collect(), pour qu'Inventory traite
 * indifféremment les deux familles. La table ARP n'est pas exposée en REST : elle est lue par
 * l'API CLI (POST /cli "show arp", commande d'affichage uniquement).
 * Testé sur 2930F WC.16.10 (API v7.0, pile VSF de 2 membres) et 2530-48G YA.16.03 (API v2.0, sans CLI).
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class AosSwitchClient
{
    private string $base;
    private ?string $prefix = null;
    private ?string $cookie = null;
    private \CurlHandle $ch;

    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private bool $verifySsl = false,
        public string $apiVersion = 'auto',
        private int $timeout = 15,
        string $protocol = 'https',
    ) {
        $this->base = ($protocol === 'http' ? 'http' : 'https') . "://{$host}";
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout * 4,
            CURLOPT_PROXY => '',               // ne pas passer par un proxy système pour joindre les switchs
            CURLOPT_NOPROXY => '*',
            // une connexion par requête : sur une connexion réutilisée (keep-alive), le 2530 met ~5 s à répondre
            // à chaque requête, contre 0,1 à 0,5 s sur une connexion neuve
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
        ]);
    }

    // ------------------------------------------------------------ HTTP
    /** @return array{0:int,1:string} */
    private function request(string $method, string $url, ?array $json = null): array
    {
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $json === null ? null : json_encode($json));
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array_values(array_filter([
            'Content-Type: application/json',
            $this->cookie ? "Cookie: {$this->cookie}" : null,
        ])));
        $body = curl_exec($this->ch);
        if ($body === false) {
            throw new AosCxException(sprintf('Connexion à %s impossible : %s', $this->host, curl_error($this->ch)));
        }
        return [(int) curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE), (string) $body];
    }

    public function get(string $path): mixed
    {
        [$code, $body] = $this->request('GET', $this->prefix . $path);
        if ($code === 404) {
            return null;
        }
        if ($code !== 200) {
            throw new AosCxException("GET {$path} -> {$code} " . mb_substr($body, 0, 200));
        }
        return json_decode($body, true);
    }

    /** Éléments d'une collection REST ArubaOS-Switch ({"..._element": [...]}, "mac-table" avant l'API v3). */
    private function elements(string $path): array
    {
        $data = $this->get($path);
        foreach (is_array($data) ? $data : [] as $k => $v) {
            if (str_ends_with((string) $k, '_element') || $k === 'blades' || $k === 'mac-table') {
                return is_array($v) ? $v : [];
            }
        }
        return [];
    }

    /** Sortie texte d'une commande d'affichage (« show … ») via l'API CLI. */
    public function cli(string $cmd): string
    {
        if (!str_starts_with($cmd, 'show ')) {
            throw new \InvalidArgumentException('Seules les commandes « show » sont autorisées');
        }
        [$code, $body] = $this->request('POST', "{$this->prefix}/cli", ['cmd' => $cmd]);
        if ($code !== 200) {
            throw new AosCxException("CLI {$cmd} -> {$code} " . mb_substr($body, 0, 200));
        }
        return (string) base64_decode(json_decode($body, true)['result_base64_encoded'] ?? '');
    }

    // ------------------------------------------------------------ session
    /** Versions d'API annoncées, de la plus ancienne à la plus récente (vide si non lisible). */
    private function versions(): array
    {
        [$code, $body] = $this->request('GET', "{$this->base}/rest/version");
        $versions = $code === 200 ? array_column(json_decode($body, true)['version_element'] ?? [], 'version') : [];
        usort($versions, fn($a, $b) => version_compare(substr($a, 1), substr($b, 1)));
        return $versions;
    }

    public function login(): void
    {
        if ($this->apiVersion && $this->apiVersion !== 'auto') {
            $this->openSession($this->apiVersion);
            return;
        }
        $versions = $this->versions();
        if (!$versions) {
            // anciens firmwares (2530 en YA.16.03) : /rest/version n'est lisible qu'une fois connecté,
            // on ouvre une session en v1 puis on se reconnecte dans la version la plus récente
            $this->openSession('v1');
            $versions = $this->versions();
            if (!$versions) {
                return;
            }
            $this->logout();
        }
        $this->openSession(end($versions));
    }

    private function openSession(string $ver): void
    {
        $this->prefix = "{$this->base}/rest/{$ver}";
        [$code, $body] = $this->request('POST', "{$this->prefix}/login-sessions",
            ['userName' => $this->username, 'password' => $this->password]);
        $cookie = json_decode($body, true)['cookie'] ?? null;
        if ($code !== 201 || !$cookie) {
            throw new AosCxException(sprintf('Échec login (%d) : %s', $code, mb_substr($body, 0, 200)));
        }
        $this->cookie = $cookie;
        $this->apiVersion = $ver;
    }

    public function logout(): void
    {
        if ($this->cookie) {
            try {
                $this->request('DELETE', "{$this->prefix}/login-sessions");
            } catch (AosCxException) {
            }
            $this->cookie = null;
        }
    }

    // ------------------------------------------------------------ données
    public function system(): array
    {
        $sys = $this->get('/system') ?: [];
        // global_info à partir de l'API v3, /system/status sur les plus anciennes
        $info = $this->get('/system/status/global_info') ?: ($this->get('/system/status') ?: []);
        $status = $this->get('/system/status/switch') ?: [];
        $blades = $status['blades'] ?? [];
        $model = ($blades[0]['product_name'] ?? '') ?: ($status['product_name'] ?? '');
        return [
            'hostname' => ($sys['name'] ?? '') ?: $this->host,
            'platform' => trim($model . (count($blades) > 1 ? ' (pile de ' . count($blades) . ')' : '')),
            'version' => $info['firmware_version'] ?? '',
            'system_mac' => AosCxClient::normMac($info['base_ethernet_address']['octets'] ?? ''),
        ];
    }

    public function interfaces(): array
    {
        // duplex / état physique (par membre de pile)
        $duplex = [];
        foreach ($this->elements('/system/status/switch') as $blade) {
            foreach ($blade['data_ports'] ?? [] as $dp) {
                $mode = (string) ($dp['oper_mode'] ?? '');
                $duplex[(string) $dp['port_name']] = str_contains($mode, 'FDX') || str_contains($mode, '_FD') ? 'full'
                    : (str_contains($mode, 'HDX') ? 'half' : '');
            }
        }
        $speed = [];
        foreach ($this->elements('/port-statistics') as $st) {
            $speed[(string) $st['id']] = (int) ($st['port_speed_mbps'] ?? 0) * 1_000_000;
        }
        // VLAN par port : non tagué = natif / accès, tagués = trunk
        $untagged = $tagged = [];
        foreach ($this->elements('/vlans-ports') as $vp) {
            $p = (string) $vp['port_id'];
            if (($vp['port_mode'] ?? '') === 'POM_UNTAGGED') {
                $untagged[$p] = (string) $vp['vlan_id'];
            } elseif (str_starts_with((string) ($vp['port_mode'] ?? ''), 'POM_TAGGED')) {
                $tagged[$p][] = (string) $vp['vlan_id'];
            }
        }
        $vlanInfo = function (string $p) use ($untagged, $tagged): array {
            $trunks = $tagged[$p] ?? [];
            sort($trunks, SORT_NUMERIC);
            return [
                'vlan_mode' => $trunks ? 'native-untagged' : 'access',
                'native_vlan' => $untagged[$p] ?? '',
                'trunks' => $trunks,
            ];
        };

        $out = $lags = [];
        foreach ($this->elements('/ports') as $it) {
            $name = (string) ($it['id'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[$name] = [
                'name' => $name,
                'type' => 'system',
                'description' => $it['name'] ?? '' ?: '',
                'admin' => !empty($it['is_port_enabled']) ? 'up' : 'down',
                'link' => !empty($it['is_port_up']) ? 'up' : 'down',
                'speed' => !empty($it['is_port_up']) ? ($speed[$name] ?? 0) : 0,
                'duplex' => !empty($it['is_port_up']) ? ($duplex[$name] ?? '') : '',
                'members' => [],
            ] + $vlanInfo($name);
            if (!empty($it['trunk_group'])) {
                $lags[(string) $it['trunk_group']][] = $name;
            }
        }
        // agrégats (Trk1, Trk2…) : construits à partir de leurs membres
        foreach ($lags as $lag => $members) {
            $m = array_map(fn($p) => $out[$p], $members);
            $out[$lag] = [
                'name' => $lag,
                'type' => 'lag',
                'description' => $m[0]['description'],
                'admin' => in_array('up', array_column($m, 'admin'), true) ? 'up' : 'down',
                'link' => in_array('up', array_column($m, 'link'), true) ? 'up' : 'down',
                'speed' => array_sum(array_column($m, 'speed')),
                'duplex' => '',
                'members' => $members,
            ] + $vlanInfo($lag);
        }
        return $out;
    }

    public function vlans(): array
    {
        $out = [];
        foreach ($this->elements('/vlans') as $v) {
            $out[(string) $v['vlan_id']] = $v['name'] ?? '' ?: '';
        }
        return $out;
    }

    public function macs(): array
    {
        $out = [];
        foreach ($this->elements('/mac-table') as $m) {
            $out[] = [
                'mac' => AosCxClient::normMac($m['mac_address'] ?? ''),
                'vlan' => (string) ($m['vlan_id'] ?? ''),
                'port' => (string) ($m['port_id'] ?? ''),
                'type' => 'dynamic',
            ];
        }
        return $out;
    }

    /** Retourne {port_local: [voisins]}. */
    public function lldp(): array
    {
        $result = [];
        foreach ($this->elements('/lldp/remote-device') as $o) {
            $caps = array_keys(array_filter($o['capabilities_enabled'] ?? [], fn($v) => $v === true));
            $ip = '';
            foreach ($o['remote_management_address'] ?? [] as $a) {
                if (($a['type'] ?? '') === 'AFM_IP4') {
                    $ip = (string) $a['address'];
                    break;
                }
            }
            $isMac = fn(string $v) => (bool) preg_match('/^[0-9a-fA-F]{2}([ :.\-]?[0-9a-fA-F]{2}){5}$/', $v);
            $cid = trim((string) ($o['chassis_id'] ?? ''));
            $pid = trim((string) ($o['port_id'] ?? ''));
            $name = $o['system_name'] ?? '' ?: '';
            // PC Windows : nom d'hôte en chassis_id et adresse MAC en port_id
            if ($name === '' && $cid !== '' && !$isMac($cid) && $isMac($pid)) {
                [$name, $cid] = [$cid, $pid];
            }
            $result[(string) $o['local_port']][] = [
                'chassis_id' => $isMac($cid) ? AosCxClient::normMac($cid) : $cid,
                'port_id' => $isMac($pid) ? AosCxClient::normMac($pid) : $pid,
                'name' => $name,
                'description' => $o['system_description'] ?? '' ?: '',
                'port_description' => $o['port_description'] ?? '' ?: '',
                'mgmt_ip' => $ip,
                'capabilities' => implode(', ', array_map(fn($c) => str_replace('_', ' ', $c), $caps)),
            ];
        }
        return $result;
    }

    /** mac -> ip, depuis « show arp » (la table ARP n'a pas d'équivalent REST). */
    public function arp(): array
    {
        $out = [];
        try {
            $text = $this->cli('show arp');
        } catch (AosCxException) {
            return $out;
        }
        foreach (preg_split('/\R/', $text) as $line) {
            if (preg_match('/^\s*(\d{1,3}(?:\.\d{1,3}){3})\s+([0-9a-fA-F]{6}-[0-9a-fA-F]{6})\s/', $line, $m)) {
                $out[AosCxClient::normMac($m[2])] = $m[1];
            }
        }
        return $out;
    }

    public function collect(): array
    {
        return ['system' => $this->system(), 'interfaces' => $this->interfaces(), 'vlans' => $this->vlans(),
            'macs' => $this->macs(), 'lldp' => $this->lldp(), 'arp' => $this->arp(), 'api_version' => $this->apiVersion];
    }
}
