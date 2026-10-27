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
        return $this->collector->snapshot() ?? ['switches' => [], 'macs' => [],
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
        foreach ($this->collector->history() as $mac => $h) {
            $mac = (string) $mac;
            if ((strlen($hexq) >= 4 && str_contains(str_replace(':', '', $mac), $hexq))
                || ($q !== '' && $q === ($h['ip'] ?? ''))) {
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
        $f = fopen('php://temp', 'r+');
        fputcsv($f, ['mac', 'nom_appareil', 'ip', 'constructeur', 'switch', 'port', 'description_port', 'vlan',
            'nom_vlan', 'role_port', 'localisation_principale', 'via'], ';', '"', '', "\r\n");
        foreach ($snap['macs'] as $m) {
            fputcsv($f, [$m['mac'], $names[$m['mac']] ?? '', $m['ip'], $m['vendor'], $m['switch_name'], $m['port'], $m['port_desc'],
                $m['vlan'], $m['vlan_name'], $m['role'], $m['primary'] ? 'oui' : 'non', $m['via']], ';', '"', '', "\r\n");
        }
        rewind($f);
        return "\xEF\xBB\xBF" . stream_get_contents($f);
    }
}
