<?php
/**
 * Agrège les données brutes de chaque switch en un inventaire unique :
 * ports classés (accès / uplink / AP / LAG), table MAC globale localisée,
 * topologie LLDP, bornes et clients des clusters Wi-Fi (Aruba Instant).
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class Inventory
{
    private const RANK = ['access' => 0, 'ap' => 1, 'ext' => 1, 'lag' => 2, 'uplink' => 3];

    // ------------------------------------------------------------------ OUI
    private static array $oui = [];

    /** Charge le fichier IEEE oui.csv (facultatif) : https://standards-oui.ieee.org/oui/oui.csv */
    public static function loadOui(string $path): int
    {
        if (!is_file($path) || !($f = fopen($path, 'r'))) {
            return 0;
        }
        while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
            if (count($row) >= 3 && preg_match('/^[0-9A-Fa-f]{6}$/', (string) $row[1])) {
                self::$oui[strtolower($row[1])] = trim($row[2]);
            }
        }
        fclose($f);
        return count(self::$oui);
    }

    public static function vendor(string $mac): string
    {
        $h = str_replace(':', '', $mac);
        if (strlen($h) !== 12 || !ctype_xdigit($h)) {
            return '';
        }
        if (hexdec($h[1]) & 0x2) {
            return 'MAC aléatoire / locale';
        }
        return self::$oui[substr($h, 0, 6)] ?? '';
    }

    // ------------------------------------------------------------------ helpers
    private static function isAp(?string $caps, ?string $desc): bool
    {
        $c = strtolower((string) $caps);
        $d = strtolower((string) $desc);
        return str_contains($c, 'wlan') || str_contains($c, 'access point') || str_contains($d, 'access point')
            || preg_match('/\bap-?\d{3}/', $d);
    }

    private static function isBridge(?string $caps): bool
    {
        $c = strtolower((string) $caps);
        return str_contains($c, 'bridge') || str_contains($c, 'router');
    }

    private static function isPhone(?string $caps): bool
    {
        return str_contains(strtolower((string) $caps), 'telephone');
    }

    public static function portSortKey(string $name): array
    {
        if (str_starts_with($name, 'lag') || str_starts_with($name, 'Trk')) {  // agrégats AOS-CX / ArubaOS-Switch
            return [9, (int) (preg_replace('/\D/', '', $name) ?: 0), 0, 0];
        }
        $parts = array_map(fn($p) => ctype_digit($p) ? (int) $p : 0, preg_split('#[/:]#', $name));
        $parts = array_pad($parts, 3, 0);
        return [0, $parts[0], $parts[1], $parts[2]];
    }

    // ------------------------------------------------------------------ build
    /**
     * $switchCfgs : config des switchs (id, host, name)
     * $raw        : {id: résultat de AosCxClient::collect() ou {"error": "..."}}
     * $history    : mac -> dernière localisation (mis à jour en place)
     * $glpi       : résultat de GlpiClient::collect() ou {"error": "..."}, null si GLPI n'est pas configuré
     */
    public static function build(array $switchCfgs, array $raw, array &$history, array $otherSwitches = [],
                                 ?array $glpi = null): array
    {
        $now = gmdate('Y-m-d\TH:i:s') . '+00:00';
        // les clusters Wi-Fi sont traités à part (pas de ports ni de table MAC)
        $wifiCfgs = array_values(array_filter($switchCfgs, fn($c) => ($c['os'] ?? '') === 'instant'));
        $switchCfgs = array_values(array_filter($switchCfgs, fn($c) => ($c['os'] ?? '') !== 'instant'));

        // Index des switchs gérés pour reconnaître les voisins LLDP
        $known = [];
        foreach ($switchCfgs as $cfg) {
            $sid = $cfg['id'];
            $known[strtolower($cfg['host'])] = $sid;
            $sysd = $raw[$sid]['system'] ?? [];
            if (!empty($sysd['hostname'])) {
                $known[strtolower($sysd['hostname'])] = $sid;
            }
            if (!empty($sysd['system_mac'])) {
                $known[$sysd['system_mac']] = $sid;
            }
        }

        // Switchs d'infrastructure non interrogés (sans API REST) : reconnus par nom ou IP
        $nodes = [];
        foreach ($otherSwitches as $o) {
            $oid = 'infra:' . (($o['host'] ?? '') ?: ($o['name'] ?? ''));
            $nodes[$oid] = ['id' => $oid, 'label' => ($o['name'] ?? '') ?: ($o['host'] ?? ''), 'kind' => 'switch',
                'managed' => false, 'infra' => true, 'status' => 'unmanaged',
                'detail' => 'Switch sans API REST (non interrogé)', 'ip' => $o['host'] ?? ''];
            foreach ([$o['host'] ?? '', $o['name'] ?? ''] as $k) {
                if ($k) {
                    $known[strtolower($k)] = $oid;
                }
            }
        }

        $matchKnown = function (array $nb) use (&$known): ?string {
            foreach ([$nb['chassis_id'] ?? '', $nb['name'] ?? '', $nb['mgmt_ip'] ?? ''] as $k) {
                $k = strtolower((string) $k);
                if ($k !== '' && isset($known[$k])) {
                    return $known[$k];
                }
            }
            return null;
        };

        // ARP global (un cœur de réseau voit souvent les IP de tout le monde)
        $arp = [];
        foreach ($raw as $r) {
            foreach ($r['arp'] ?? [] as $mac => $ip) {
                $arp[(string) $mac] = $ip;
            }
            // les bornes Wi-Fi connaissent l'IP de leurs clients
            foreach ($r['wifi']['clients'] ?? [] as $c) {
                if ($c['mac'] !== '' && filter_var($c['ip'], FILTER_VALIDATE_IP) && $c['ip'] !== '0.0.0.0') {
                    $arp[$c['mac']] ??= $c['ip'];
                }
            }
        }

        $switches = [];
        $allMacs = [];
        $links = [];

        foreach ($switchCfgs as $cfg) {
            $sid = $cfg['id'];
            $r = $raw[$sid] ?? ['error' => 'Pas encore interrogé'];
            $sysd = $r['system'] ?? [];
            $sw = [
                'id' => $sid,
                'host' => $cfg['host'],
                'name' => ($cfg['name'] ?? '') ?: (($sysd['hostname'] ?? '') ?: $cfg['host']),
                'hostname' => $sysd['hostname'] ?? '',
                'platform' => $sysd['platform'] ?? '',
                'version' => $sysd['version'] ?? '',
                'system_mac' => $sysd['system_mac'] ?? '',
                'api_version' => $r['api_version'] ?? '',
                'site' => $cfg['site'] ?? '',
                'error' => $r['error'] ?? null,
                'collected_at' => $r['collected_at'] ?? null,
                'ports' => [],
            ];
            $nodes[$sid] = ['id' => $sid, 'label' => $sw['name'], 'kind' => 'switch', 'managed' => true,
                'status' => $sw['error'] ? 'error' : 'ok',
                'detail' => trim("{$sw['platform']} {$sw['version']}") ?: $cfg['host'],
                'ip' => $cfg['host']];
            if ($sw['error']) {
                $switches[] = $sw;
                continue;
            }

            $itfs = $r['interfaces'] ?? [];
            $lldp = $r['lldp'] ?? [];
            $vlans = $r['vlans'] ?? [];
            $macsByPort = [];
            foreach ($r['macs'] ?? [] as $m) {
                $macsByPort[$m['port']][] = $m;
            }

            // Rôle de chaque port
            $roles = [];
            foreach ($lldp as $pname => $nbs) {
                $role = 'access';
                foreach ($nbs as $nb) {
                    if ($matchKnown($nb)) {
                        $role = 'uplink';
                        break;
                    }
                    if (self::isAp($nb['capabilities'] ?? '', $nb['description'] ?? '')) {
                        $role = 'ap';
                    } elseif (self::isBridge($nb['capabilities'] ?? '') && !self::isPhone($nb['capabilities'] ?? '')
                        && $role !== 'ap') {
                        $role = 'ext';  // switch tiers / non géré en aval
                    }
                }
                $roles[(string) $pname] = $role;
            }
            foreach ($itfs as $pname => $itf) {
                if (!empty($itf['members'])) {
                    $mr = array_map(fn($m) => $roles[$m] ?? 'access', $itf['members']);
                    $roles[(string) $pname] = in_array('uplink', $mr, true) ? 'uplink'
                        : (in_array('ap', $mr, true) ? 'ap' : (in_array('ext', $mr, true) ? 'ext' : 'lag'));
                }
            }
            // membres de LAG -> on hérite du rôle du LAG
            $lagOf = [];
            foreach ($itfs as $pname => $itf) {
                foreach ($itf['members'] ?? [] as $m) {
                    $lagOf[$m] = (string) $pname;
                }
            }
            $roleOf = fn(string $p) => ($roles[$p] ?? null) ?: (isset($lagOf[$p]) ? ($roles[$lagOf[$p]] ?? null) : null)
                ?: 'access';

            $pnames = array_map('strval', array_keys($itfs));
            usort($pnames, fn($a, $b) => self::portSortKey($a) <=> self::portSortKey($b));
            foreach ($pnames as $pname) {
                $pmacs = $macsByPort[$pname] ?? [];
                $sw['ports'][] = $itfs[$pname] + [
                    'role' => $roleOf($pname),
                    'lag' => $lagOf[$pname] ?? '',
                    'mac_count' => count($pmacs),
                    'macs' => array_map(fn($m) => ['mac' => $m['mac'], 'vlan' => $m['vlan'],
                        'ip' => $arp[$m['mac']] ?? '', 'vendor' => self::vendor($m['mac'])],
                        array_slice($pmacs, 0, 500)),
                    'lldp' => $lldp[$pname] ?? [],
                ];
            }

            // MAC globales
            foreach ($r['macs'] ?? [] as $m) {
                $prole = $roleOf($m['port']);
                $nbs = $lldp[$m['port']] ?? [];
                if (!$nbs) {
                    foreach ($itfs[$m['port']]['members'] ?? [] as $x) {
                        if (!empty($lldp[$x])) {
                            $nbs = $lldp[$x];
                            break;
                        }
                    }
                }
                $allMacs[] = [
                    'mac' => $m['mac'], 'vlan' => $m['vlan'], 'vlan_name' => $vlans[$m['vlan']] ?? '',
                    'switch' => $sid, 'switch_name' => $sw['name'], 'port' => $m['port'],
                    'port_desc' => $itfs[$m['port']]['description'] ?? '',
                    'role' => $prole, 'type' => $m['type'] ?? '',
                    'ip' => $arp[$m['mac']] ?? '', 'vendor' => self::vendor($m['mac']),
                    'via' => $nbs && in_array($prole, ['ap', 'ext', 'uplink'], true) ? $nbs[0]['name'] : '',
                ];
            }

            // Topologie
            foreach ($lldp as $pname => $nbs) {
                foreach ($nbs as $nb) {
                    $target = $matchKnown($nb);
                    if (!$target) {
                        $key = ($nb['chassis_id'] ?? '') ?: (($nb['name'] ?? '') ?: "{$sid}:{$pname}");
                        $caps = $nb['capabilities'] ?? '';
                        $kind = self::isAp($caps, $nb['description'] ?? '') ? 'ap'
                            : (self::isPhone($caps) ? 'phone' : (self::isBridge($caps) ? 'switch' : 'host'));
                        $target = 'ext:' . $key;
                        $nodes[$target] ??= ['id' => $target,
                            'label' => ($nb['name'] ?? '') ?: (($nb['chassis_id'] ?? '') ?: '?'),
                            'kind' => $kind, 'managed' => false, 'status' => 'unmanaged',
                            'detail' => mb_substr((string) ($nb['description'] ?? ''), 0, 120),
                            'ip' => $nb['mgmt_ip'] ?? ''];
                    }
                    $pair = [[$sid, (string) $pname], [$target, ($nb['port_id'] ?? '') ?: '?']];
                    usort($pair, fn($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
                    $k = json_encode($pair);
                    $links[$k] ??= ['a' => $pair[0][0], 'a_port' => $pair[0][1],
                        'b' => $pair[1][0], 'b_port' => $pair[1][1]];
                }
            }

            $up = $phys = 0;
            foreach ($sw['ports'] as $p) {
                if ($p['type'] !== 'lag') {
                    $phys++;
                    $up += $p['link'] === 'up' ? 1 : 0;
                }
            }
            $sw['stats'] = ['ports_up' => $up, 'ports_total' => $phys,
                'macs' => count($r['macs'] ?? []),
                'lldp' => array_sum(array_map('count', $lldp)),
                'vlans' => count($vlans)];
            $switches[] = $sw;
        }

        // Localisation : pour chaque MAC, quelles observations sont sur un port d'accès ?
        $best = [];
        foreach ($allMacs as $i => $m) {
            $cur = $best[$m['mac']] ?? null;
            if ($cur === null || self::RANK[$m['role']] < self::RANK[$allMacs[$cur]['role']]) {
                $best[$m['mac']] = $i;
            }
        }
        foreach ($allMacs as $i => &$m) {
            $m['primary'] = $best[$m['mac']] === $i;
        }
        unset($m);
        foreach ($best as $mac => $i) {
            $m = $allMacs[$i];
            $h = $history[$mac] ?? ['first_seen' => $now];
            unset($h['ap'], $h['essid']);   // remis plus bas si l'appareil est en Wi-Fi
            $h = array_merge($h, ['last_seen' => $now, 'switch' => $m['switch'], 'switch_name' => $m['switch_name'],
                'port' => $m['port'], 'vlan' => $m['vlan'], 'role' => $m['role']]);
            if ($m['ip']) {
                $h['ip'] = $m['ip'];
            }
            $history[(string) $mac] = $h;
        }

        $wifi = self::wifi($wifiCfgs, $raw, $switches, $allMacs, $best, $arp, $history, $now);

        // GLPI : fiche du parc de chaque appareil vu (maintenant ou dans l'historique), par adresse MAC
        $glpiOut = null;
        $inGlpi = 0;
        if ($glpi !== null) {
            $byMac = $glpi['glpi']['macs'] ?? [];
            $seen = $best;
            foreach ($wifi as $w) {
                foreach ($w['clients'] as $c) {
                    $seen[$c['mac']] = true;
                }
            }
            $macs = [];
            foreach ($history as $mac => &$h) {
                if (isset($byMac[$mac])) {
                    $macs[$mac] = $byMac[$mac];
                    $h['glpi_name'] = $byMac[$mac]['name'];
                    $inGlpi += isset($seen[$mac]) ? 1 : 0;
                }
            }
            unset($h);
            $glpiOut = ['url' => $glpi['glpi']['url'] ?? '', 'error' => $glpi['error'] ?? null,
                'collected_at' => $glpi['collected_at'] ?? null, 'assets' => $glpi['glpi']['assets'] ?? 0, 'macs' => $macs];
        }

        // Les liens entre switchs gérés en double (chaque côté le voit) sont déjà fusionnés par la clé triée
        $sum = fn(string $k) => array_sum(array_map(fn($s) => $s['stats'][$k] ?? 0, $switches));
        return [
            'collected_at' => $now,
            'switches' => $switches,
            'macs' => $allMacs,
            'wifi' => $wifi,
            'glpi' => $glpiOut,
            'topology' => ['nodes' => array_values($nodes), 'links' => array_values($links)],
            'summary' => [
                'switches' => count($switches),
                'switches_ok' => count(array_filter($switches, fn($s) => !$s['error'])),
                'macs_unique' => count($best),
                'ports_up' => $sum('ports_up'),
                'ports_total' => $sum('ports_total'),
                'wifi_aps' => array_sum(array_map(fn($w) => count($w['aps']), $wifi)),
                'wifi_clients' => array_sum(array_map(fn($w) => count($w['clients']), $wifi)),
                'glpi_matched' => $inGlpi,
            ],
        ];
    }

    /**
     * Clusters Wi-Fi : chaque borne est située sur le port de switch où elle est branchée (voisin LLDP
     * annonçant son nom ou son IP, sinon sa MAC dans les tables), ses clients en héritent.
     */
    private static function wifi(array $cfgs, array $raw, array $switches, array $allMacs, array $best, array $arp,
                                 array &$history, string $now): array
    {
        $byLldp = [];
        foreach ($switches as $sw) {
            foreach ($sw['ports'] as $p) {
                foreach ($p['lldp'] as $nb) {
                    $loc = ['switch' => $sw['id'], 'switch_name' => $sw['name'], 'port' => $p['name']];
                    foreach ([$nb['name'] ?? '', $nb['mgmt_ip'] ?? '', $nb['chassis_id'] ?? ''] as $k) {
                        if ($k !== '') {
                            $byLldp[strtolower($k)] ??= $loc;
                        }
                    }
                }
            }
        }
        $macOfIp = array_flip($arp);
        $locate = function (array $ap) use ($byLldp, $macOfIp, $allMacs, $best): ?array {
            foreach ([$ap['name'], $ap['ip']] as $k) {
                if ($k !== '' && isset($byLldp[strtolower($k)])) {
                    return $byLldp[strtolower($k)];
                }
            }
            // borne sans nom : son nom est sa MAC ; sinon MAC retrouvée par l'IP
            $mac = preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $ap['name']) ? $ap['name'] : ($macOfIp[$ap['ip']] ?? null);
            if ($mac !== null && isset($best[$mac]) && $allMacs[$best[$mac]]['role'] !== 'uplink') {
                $m = $allMacs[$best[$mac]];
                return ['switch' => $m['switch'], 'switch_name' => $m['switch_name'], 'port' => $m['port']];
            }
            return null;
        };

        $out = [];
        foreach ($cfgs as $cfg) {
            $r = $raw[$cfg['id']] ?? ['error' => 'Pas encore interrogé'];
            $w = $r['wifi'] ?? [];
            $cluster = [
                'id' => $cfg['id'],
                'host' => $cfg['host'],
                'name' => ($cfg['name'] ?? '') ?: (($w['name'] ?? '') ?: $cfg['host']),
                'site' => ($cfg['site'] ?? '') ?: ($w['location'] ?? ''),
                'version' => $w['version'] ?? '',
                'error' => $r['error'] ?? null,
                'collected_at' => $r['collected_at'] ?? null,
                'aps' => [],
                'clients' => [],
            ];
            $apLoc = [];
            foreach ($w['aps'] ?? [] as $ap) {
                $loc = $locate($ap);
                $apLoc[$ap['name']] = $loc;
                $cluster['aps'][] = $ap + ['switch' => $loc['switch'] ?? '', 'switch_name' => $loc['switch_name'] ?? '',
                    'port' => $loc['port'] ?? ''];
            }
            foreach ($w['clients'] ?? [] as $c) {
                $loc = $apLoc[$c['ap']] ?? null;
                $c += ['cluster' => $cfg['id'], 'cluster_name' => $cluster['name'], 'vendor' => self::vendor($c['mac']),
                    'switch' => $loc['switch'] ?? '', 'switch_name' => $loc['switch_name'] ?? '', 'port' => $loc['port'] ?? ''];
                $cluster['clients'][] = $c;

                $h = $history[$c['mac']] ?? ['first_seen' => $now];
                if (!isset($best[$c['mac']]) || $allMacs[$best[$c['mac']]]['role'] !== 'access') {
                    $h = array_merge($h, ['switch' => $c['switch'], 'switch_name' => $c['switch_name'], 'port' => $c['port']]);
                }
                $h = array_merge($h, ['last_seen' => $now, 'ap' => $c['ap'], 'essid' => $c['essid'], 'name' => $c['name']]);
                if ($c['ip'] !== '') {
                    $h['ip'] = $c['ip'];
                }
                $history[$c['mac']] = $h;
            }
            $out[] = $cluster;
        }
        return $out;
    }
}
