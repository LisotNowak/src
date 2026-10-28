<?php
/**
 * Réponses de l'API JSON utilisée par l'interface (App\Controller\Aruba\ArubaController).
 * L'accès est contrôlé par le composant Security de Symfony.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class Api
{
    public function __construct(private Collector $collector)
    {
    }

    public function snapshot(): array
    {
        return $this->collector->snapshot() ?? ['switches' => [], 'macs' => [], 'wifi' => [], 'glpi' => null,
            'topology' => ['nodes' => [], 'links' => []], 'summary' => (object) [], 'collected_at' => null];
    }

    public function status(): array
    {
        return $this->collector->status();
    }

    public function refresh(): array
    {
        return $this->collector->launch() ? ['started' => true]
            : ['started' => false, 'message' => 'Collecte déjà en cours'];
    }

    public function history(string $q): array
    {
        $q = strtolower(trim($q));
        $hexq = preg_replace('/[^0-9a-f]/', '', $q);
        $out = [];
        $glpi = $this->collector->snapshot()['glpi']['macs'] ?? [];
        foreach ($this->collector->history() as $mac => $h) {
            $mac = (string) $mac;
            $g = $glpi[$mac] ?? [];
            $names = strtolower(implode(' ', [$h['name'] ?? '', $h['glpi_name'] ?? '', $g['user'] ?? '', $g['contact'] ?? '',
                $g['serial'] ?? '', $g['model'] ?? '']));
            if ((strlen($hexq) >= 4 && preg_match('/^[0-9a-f:.\-\s]+$/', $q) && str_contains(str_replace(':', '', $mac), $hexq))
                || ($q !== '' && $q === ($h['ip'] ?? ''))
                || (strlen($q) >= 2 && str_contains($names, $q))) {
                $out[] = ['mac' => $mac] + $h;
            }
        }
        usort($out, fn($a, $b) => strcmp($b['last_seen'] ?? '', $a['last_seen'] ?? ''));
        return array_slice($out, 0, 200);
    }

    public function macsCsv(): string
    {
        $snap = $this->collector->snapshot() ?? ['macs' => [], 'switches' => []];
        // Les tables MAC ne donnent pas de nom : seul un voisin LLDP (borne, switch, téléphone…) annonce le sien
        $names = [];
        foreach ($snap['switches'] as $sw) {
            foreach ($sw['ports'] ?? [] as $p) {
                foreach ($p['lldp'] ?? [] as $nb) {
                    if (($nb['name'] ?? '') !== '' && $nb['name'] !== $nb['chassis_id']) {
                        $names[$nb['chassis_id']] = $nb['name'];
                    }
                }
            }
        }
        // les bornes Wi-Fi donnent le nom de leurs clients
        $wifi = [];
        foreach ($snap['wifi'] ?? [] as $cluster) {
            foreach ($cluster['clients'] as $c) {
                $wifi[$c['mac']] = $c;
                if ($c['name'] !== '' && $c['name'] !== $c['mac']) {
                    $names[$c['mac']] ??= $c['name'];
                }
            }
        }
        // fiche GLPI de l'appareil (colonnes vides si GLPI n'est pas configuré ou ne connaît pas la MAC)
        $glpi = $snap['glpi']['macs'] ?? [];
        foreach ($glpi as $mac => $g) {
            $names[$mac] ??= $g['name'];
        }
        $glpiCols = fn(string $mac) => isset($glpi[$mac])
            ? [$glpi[$mac]['type_label'], $glpi[$mac]['name'], $glpi[$mac]['user'] ?: $glpi[$mac]['contact'], $glpi[$mac]['model'],
                $glpi[$mac]['serial'], $glpi[$mac]['location'], $glpi[$mac]['url']]
            : array_fill(0, 7, '');
        $f = fopen('php://temp', 'r+');
        fputcsv($f, ['mac', 'nom_appareil', 'ip', 'constructeur', 'switch', 'port', 'description_port', 'vlan',
            'nom_vlan', 'role_port', 'localisation_principale', 'via', 'glpi_type', 'glpi_nom', 'glpi_utilisateur',
            'glpi_modele', 'glpi_numero_serie', 'glpi_lieu', 'glpi_lien'], ';', '"', '', "\r\n");
        $seen = [];
        foreach ($snap['macs'] as $m) {
            $seen[$m['mac']] = true;
            fputcsv($f, [$m['mac'], $names[$m['mac']] ?? '', $m['ip'], $m['vendor'], $m['switch_name'], $m['port'], $m['port_desc'],
                $m['vlan'], $m['vlan_name'], $m['role'], $m['primary'] ? 'oui' : 'non', $m['via'] ?: ($wifi[$m['mac']]['ap'] ?? ''),
                ...$glpiCols($m['mac'])], ';', '"', '', "\r\n");
        }
        // clients Wi-Fi absents des tables MAC des switchs interrogés
        foreach ($wifi as $mac => $c) {
            if (!isset($seen[$mac])) {
                fputcsv($f, [$mac, $names[$mac] ?? '', $c['ip'], $c['vendor'], $c['switch_name'], $c['port'], 'Wi-Fi ' . $c['essid'],
                    '', '', 'wifi', 'oui', $c['ap'], ...$glpiCols($mac)], ';', '"', '', "\r\n");
            }
        }
        rewind($f);
        return "\xEF\xBB\xBF" . stream_get_contents($f);
    }
}
