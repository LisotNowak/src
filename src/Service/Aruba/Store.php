<?php
/**
 * Base de données de Gestion switch (schéma gestion_switch, entités dans App\Entity\gestion_switch) :
 * équipements, collectes (photo du réseau en JSONB et résumé), appareils vus et leurs déplacements,
 * état détaillé du réseau (ports, voisins LLDP, VLAN, tables MAC, bornes et clients Wi-Fi, fiches GLPI),
 * changements des ports, présence des appareils à chaque collecte. Vues v_appareil, v_deplacement, v_port.
 *
 * Chaque collecte est enregistrée en une transaction par record(). SQL direct plutôt que l'ORM :
 * quelques milliers de lignes par collecte et des types PostgreSQL (macaddr, inet, jsonb), sur un serveur
 * distant (le temps dépend du nombre de requêtes : écritures regroupées, voir insertRows()).
 * Les dates sont en UTC. La configuration des switchs (table equipement) passe par l'ORM, dans Config.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

use Doctrine\DBAL\Connection;

class Store
{
    /** Appareils au format de l'ancien history.json (voir historyEntry()) */
    private const HISTORY_SQL = 'SELECT a.mac::text AS mac, a.premiere_vue, a.derniere_vue, host(a.ip) AS ip, a.nom, a.glpi_nom,
            a.port, a.vlan, a.role, a.borne, a.essid, e.code, e.nom AS e_nom, e.hostname AS e_hostname, e.hote AS e_hote
        FROM gestion_switch.appareil a LEFT JOIN gestion_switch.equipement e ON e.id = a.equipement_id';

    public function __construct(private Connection $db)
    {
    }

    // ════════════════════════════════════════════════════════════ lecture
    /** Photo du réseau de la dernière collecte (JSON brut, renvoyé tel quel à la page), null si aucune. */
    public function lastSnapshotJson(): ?string
    {
        $json = $this->db->fetchOne('SELECT snapshot FROM gestion_switch.collecte
            WHERE snapshot IS NOT NULL ORDER BY debut DESC, id DESC LIMIT 1');
        return $json === false ? null : (string) $json;
    }

    public function lastSnapshot(): ?array
    {
        $json = $this->lastSnapshotJson();
        return $json === null ? null : json_decode($json, true);
    }

    /** Date de la dernière collecte, sans lire toute la photo (interrogé chaque seconde pendant une collecte). */
    public function lastCollectedAt(): ?string
    {
        $v = $this->db->fetchOne("SELECT snapshot->>'collected_at' FROM gestion_switch.collecte
            WHERE snapshot IS NOT NULL ORDER BY debut DESC, id DESC LIMIT 1");
        return $v === false ? null : $v;
    }

    /** Début (timestamp Unix) de la dernière collecte, réussie ou non : point de départ de la collecte automatique. */
    public function lastDebut(): ?int
    {
        $v = $this->db->fetchOne('SELECT EXTRACT(EPOCH FROM max(debut))::bigint FROM gestion_switch.collecte');
        return $v === false || $v === null ? null : (int) $v;
    }

    /** Tous les appareils connus : mac => [first_seen, last_seen, switch, switch_name, port, vlan…] */
    public function loadHistory(): array
    {
        $out = [];
        foreach ($this->db->iterateAssociative(self::HISTORY_SQL) as $r) {
            $out[$r['mac']] = self::historyEntry($r);
        }
        return $out;
    }

    /**
     * Recherche dans les appareils connus (vue Recherche, quand la collecte actuelle ne trouve rien).
     * $hex : chiffres hexadécimaux d'une MAC partielle ('' si la recherche n'en est pas une),
     * $extraMacs : appareils retenus d'après leur fiche GLPI (utilisateur, numéro de série…).
     */
    public function searchHistory(string $q, string $hex, array $extraMacs): array
    {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $rows = $this->db->fetchAllAssociative(self::HISTORY_SQL . "
            WHERE (?::text <> '' AND replace(a.mac::text, ':', '') LIKE ?::text)
               OR host(a.ip) = ?::text
               OR (?::boolean AND (a.nom ILIKE ?::text OR a.glpi_nom ILIKE ?::text))
               OR a.mac = ANY(?::macaddr[])
            ORDER BY a.derniere_vue DESC LIMIT 200",
            [$hex, '%' . $hex . '%', $q, mb_strlen($q) >= 2 ? 't' : 'f', $like, $like,
                self::pgArray(array_filter(array_map(self::mac(...), $extraMacs)))]);
        return array_map(fn(array $r) => ['mac' => $r['mac']] + self::historyEntry($r), $rows);
    }

    /** Positions successives d'un appareil, de la plus récente à la plus ancienne. */
    public function mouvements(string $mac): array
    {
        $mac = self::mac($mac);
        if ($mac === null) {
            return [];
        }
        $rows = $this->db->fetchAllAssociative('SELECT m.vu_le, m.port, m.vlan, host(m.ip) AS ip, m.borne, m.essid, m.nature,
                e.code, e.nom AS e_nom, e.hostname AS e_hostname, e.hote AS e_hote
            FROM gestion_switch.appareil_mouvement m LEFT JOIN gestion_switch.equipement e ON e.id = m.equipement_id
            WHERE m.mac = ?::macaddr ORDER BY m.vu_le DESC, m.id DESC LIMIT 100', [$mac]);
        return array_map(fn(array $r) => [
            'seen' => self::iso($r['vu_le']),
            'switch' => (string) $r['code'],
            'switch_name' => self::switchName($r),
            'port' => (string) $r['port'],
            'vlan' => (string) $r['vlan'],
            'ip' => (string) $r['ip'],
            'ap' => (string) $r['borne'],
            'essid' => (string) $r['essid'],
            'nature' => (string) $r['nature'],
        ], $rows);
    }

    /**
     * Déplacements des $jours derniers jours, avec la position précédente.
     * $wifi : inclure les changements de borne Wi-Fi, $arrivees : inclure les nouveaux appareils,
     * $switch : code d'un équipement (avant ou après), $q : nom, MAC ou IP.
     */
    public function deplacements(int $jours, bool $wifi, bool $arrivees, string $switch, string $q): array
    {
        $where = ['d.vu_le >= ?::timestamp'];
        $params = [self::utc(new \DateTimeImmutable("-{$jours} days"))];
        if (!$arrivees) {
            $where[] = 'd.precedent IS NOT NULL';
        }
        if (!$wifi) {
            $where[] = "COALESCE(d.nature, '') <> 'wifi'";
        }
        if ($switch !== '') {
            $where[] = '(e.code = ? OR e0.code = ?)';
            array_push($params, $switch, $switch);
        }
        if ($q !== '') {
            $like = '%' . addcslashes(strtolower($q), '%_\\') . '%';
            $where[] = "(COALESCE(a.nom, '') ILIKE ? OR COALESCE(a.glpi_nom, '') ILIKE ? OR d.mac::text LIKE ? OR host(d.ip) = ?)";
            array_push($params, $like, $like, $like, $q);
        }
        $rows = $this->db->fetchAllAssociative("SELECT d.mac::text AS mac, d.vu_le, d.port, d.vlan, host(d.ip) AS ip,
                d.borne, d.nature, d.port_avant, d.vlan_avant, d.borne_avant, d.precedent,
                COALESCE(NULLIF(a.nom, ''), a.glpi_nom) AS nom,
                e.code, e.nom AS e_nom, e.hostname AS e_hostname, e.hote AS e_hote,
                e0.code AS code0, e0.nom AS e0_nom, e0.hostname AS e0_hostname, e0.hote AS e0_hote
            FROM (SELECT m.id, m.mac, m.vu_le, m.equipement_id, m.port, m.vlan, m.ip, m.borne, m.nature,
                    LAG(m.borne) OVER w AS borne_avant, LAG(m.id) OVER w AS precedent, LAG(m.equipement_id) OVER w AS equipement_avant_id,
                    LAG(m.port) OVER w AS port_avant, LAG(m.vlan) OVER w AS vlan_avant
                  FROM gestion_switch.appareil_mouvement m
                  WINDOW w AS (PARTITION BY m.mac ORDER BY m.vu_le, m.id)) d
            JOIN gestion_switch.appareil a ON a.mac = d.mac
            LEFT JOIN gestion_switch.equipement e ON e.id = d.equipement_id
            LEFT JOIN gestion_switch.equipement e0 ON e0.id = d.equipement_avant_id
            WHERE " . implode(' AND ', $where) . ' ORDER BY d.vu_le DESC, d.id DESC LIMIT 500', $params);
        return array_map(fn(array $r) => [
            'seen' => self::iso($r['vu_le']),
            'mac' => $r['mac'],
            'name' => (string) $r['nom'],
            'ip' => (string) $r['ip'],
            'nature' => $r['precedent'] === null ? 'arrivee' : ((string) $r['nature'] ?: 'filaire'),
            'before' => $r['precedent'] === null ? null : [
                'switch' => (string) $r['code0'], 'switch_name' => self::switchName($r, 'e0_', 'code0'),
                'port' => (string) $r['port_avant'], 'vlan' => (string) $r['vlan_avant'], 'ap' => (string) $r['borne_avant'],
            ],
            'after' => [
                'switch' => (string) $r['code'], 'switch_name' => self::switchName($r),
                'port' => (string) $r['port'], 'vlan' => (string) $r['vlan'], 'ap' => (string) $r['borne'],
            ],
        ], $rows);
    }

    /**
     * Historique d'un port : état du lien, changements du port et appareils qui y ont été branchés
     * (d'après les déplacements : arrivée, départ quand l'appareil est vu ailleurs).
     */
    public function portHistorique(string $code, string $port): array
    {
        $eq = $this->equipementIds()[$code] ?? null;
        $out = ['state' => null, 'events' => [], 'devices' => [], 'tracked_since' => null];
        if ($eq === null) {
            return $out;
        }
        $rows = $this->db->fetchAllAssociative("SELECT x.mac::text AS mac, x.vu_le, x.parti_le, a.derniere_vue,
                COALESCE(NULLIF(a.nom, ''), a.glpi_nom) AS nom, host(x.ip) AS ip, x.vlan
            FROM (SELECT m.*, LEAD(m.vu_le) OVER (PARTITION BY m.mac ORDER BY m.vu_le, m.id) AS parti_le
                  FROM gestion_switch.appareil_mouvement m
                  WHERE m.mac IN (SELECT mac FROM gestion_switch.appareil_mouvement WHERE equipement_id = ? AND port = ?)) x
            JOIN gestion_switch.appareil a ON a.mac = x.mac
            WHERE x.equipement_id = ? AND x.port = ?
            ORDER BY COALESCE(x.parti_le, a.derniere_vue) DESC, x.vu_le DESC LIMIT 200", [$eq, $port, $eq, $port]);
        $out['devices'] = array_map(fn(array $r) => [
            'mac' => $r['mac'],
            'name' => (string) $r['nom'],
            'ip' => (string) $r['ip'],
            'vlan' => (string) $r['vlan'],
            'from' => self::iso($r['vu_le']),
            'until' => self::iso($r['parti_le'] ?? $r['derniere_vue']),
            'here' => $r['parti_le'] === null,   // dernière position connue de l'appareil
        ], $rows);
        $st = $this->db->fetchAssociative('SELECT lien, admin, etat_depuis FROM gestion_switch.port
            WHERE equipement_id = ? AND nom = ?', [$eq, $port]);
        $out['state'] = $st ? ['link' => $st['lien'], 'admin' => $st['admin'], 'since' => self::iso($st['etat_depuis'])] : null;
        $out['tracked_since'] = self::iso($this->db->fetchOne('SELECT min(etat_depuis) FROM gestion_switch.port
            WHERE equipement_id = ?', [$eq]) ?: null);
        $out['events'] = array_map(fn(array $r) => [
            'seen' => self::iso($r['vu_le']), 'field' => $r['champ'], 'before' => $r['avant'], 'after' => $r['apres'],
        ], $this->db->fetchAllAssociative('SELECT vu_le, champ, avant, apres FROM gestion_switch.port_evenement
            WHERE equipement_id = ? AND port = ? ORDER BY vu_le DESC, id DESC LIMIT 100', [$eq, $port]));
        return $out;
    }

    /** Changements des ports des $jours derniers jours. */
    public function evenements(int $jours, string $switch): array
    {
        $params = [self::utc(new \DateTimeImmutable("-{$jours} days"))];
        $filter = '';
        if ($switch !== '') {
            $filter = 'AND e.code = ?';
            $params[] = $switch;
        }
        $rows = $this->db->fetchAllAssociative("SELECT v.vu_le, v.port, v.champ, v.avant, v.apres, p.description,
                e.code, e.nom AS e_nom, e.hostname AS e_hostname, e.hote AS e_hote
            FROM gestion_switch.port_evenement v
            JOIN gestion_switch.equipement e ON e.id = v.equipement_id
            LEFT JOIN gestion_switch.port p ON p.equipement_id = v.equipement_id AND p.nom = v.port
            WHERE v.vu_le >= ?::timestamp {$filter}
            ORDER BY v.vu_le DESC, v.id DESC LIMIT 500", $params);
        return array_map(fn(array $r) => [
            'seen' => self::iso($r['vu_le']), 'switch' => $r['code'], 'switch_name' => self::switchName($r),
            'port' => $r['port'], 'description' => (string) $r['description'],
            'field' => $r['champ'], 'before' => $r['avant'], 'after' => $r['apres'],
        ], $rows);
    }

    /** Ports physiques activés mais sans lien depuis au moins $jours jours. */
    public function portsInactifs(int $jours, string $switch): array
    {
        $params = [self::utc(new \DateTimeImmutable("-{$jours} days"))];
        $filter = '';
        if ($switch !== '') {
            $filter = 'AND e.code = ?';
            $params[] = $switch;
        }
        $rows = $this->db->fetchAllAssociative("SELECT p.nom, p.description, p.connecteur, p.module, p.vlan_natif, p.etat_depuis,
                e.code, e.nom AS e_nom, e.hostname AS e_hostname, e.hote AS e_hote, e.site
            FROM gestion_switch.port p JOIN gestion_switch.equipement e ON e.id = p.equipement_id
            WHERE COALESCE(p.type, '') <> 'lag' AND p.lien = 'down' AND p.admin = 'up' AND e.actif
              AND p.etat_depuis <= ?::timestamp {$filter}
            ORDER BY e.position, p.id", $params);
        return [
            'tracked_since' => self::iso($this->db->fetchOne('SELECT min(etat_depuis) FROM gestion_switch.port') ?: null),
            'ports' => array_map(fn(array $r) => [
                'switch' => $r['code'], 'switch_name' => self::switchName($r), 'site' => (string) $r['site'],
                'port' => $r['nom'], 'description' => (string) $r['description'], 'connector' => (string) $r['connecteur'],
                'module' => (string) $r['module'], 'vlan' => (string) $r['vlan_natif'], 'since' => self::iso($r['etat_depuis']),
            ], $rows),
        ];
    }

    /** Dernières collectes avec leur résumé et les équipements en erreur. */
    public function collectes(int $limit = 100): array
    {
        $rows = $this->db->fetchAllAssociative('SELECT c.id, c.debut, c.fin, c.statut, c.lance_par, c.duree, c.nb_equipements,
                c.nb_equipements_ok, c.nb_mac_uniques, c.ports_actifs, c.ports_total, c.nb_bornes, c.nb_clients_wifi, c.nb_glpi,
                c.snapshot IS NOT NULL AS photo
            FROM gestion_switch.collecte c ORDER BY c.debut DESC, c.id DESC LIMIT ' . max(1, $limit));
        $errors = [];
        if ($rows) {
            foreach ($this->db->fetchAllAssociative('SELECT ce.collecte_id, ce.erreur, e.code, e.nom AS e_nom,
                    e.hostname AS e_hostname, e.hote AS e_hote
                FROM gestion_switch.collecte_equipement ce JOIN gestion_switch.equipement e ON e.id = ce.equipement_id
                WHERE ce.statut = \'erreur\' AND ce.collecte_id = ANY(?::int[])',
                [self::pgArray(array_column($rows, 'id'))]) as $r) {
                $errors[$r['collecte_id']][] = ['switch_name' => self::switchName($r), 'error' => (string) $r['erreur']];
            }
        }
        $int = fn($v) => $v === null ? null : (int) $v;
        return array_map(fn(array $r) => [
            'id' => (int) $r['id'], 'start' => self::iso($r['debut']), 'end' => self::iso($r['fin']),
            'status' => $r['statut'], 'by' => (string) $r['lance_par'], 'duration' => $int($r['duree']),
            'devices' => $int($r['nb_equipements']), 'devices_ok' => $int($r['nb_equipements_ok']),
            'macs' => $int($r['nb_mac_uniques']), 'ports_up' => $int($r['ports_actifs']), 'ports_total' => $int($r['ports_total']),
            'aps' => $int($r['nb_bornes']), 'wifi_clients' => $int($r['nb_clients_wifi']), 'glpi' => $int($r['nb_glpi']),
            'snapshot' => (bool) $r['photo'], 'errors' => $errors[$r['id']] ?? [],
        ], $rows);
    }

    // ════════════════════════════════════════════════════════════ écriture
    /**
     * Enregistre une collecte (une seule transaction), retourne son id.
     *
     * $snap     : résultat d'Inventory::build()
     * $jobs     : code de l'équipement => ['statut' => 'ok'|'erreur', 'duree' => secondes, 'erreur' => message|null]
     * $history  : appareils après la collecte (mis à jour par Inventory::build())
     * $previous : appareils avant la collecte (loadHistory()) : seuls les changements sont écrits
     * $retentionDays : au-delà, la photo des anciennes collectes est effacée (la ligne reste) ; 0 = jamais
     * $presenceDays  : au-delà, les périodes de présence des appareils sont effacées ; 0 = jamais
     * $fullDays      : au-delà, une seule photo par jour est gardée ; 0 = toutes
     */
    public function record(array $snap, array $jobs, array $history, array $previous, \DateTimeImmutable $debut,
                           ?string $lancePar = null, int $retentionDays = 0, int $presenceDays = 0, int $fullDays = 7): int
    {
        return $this->db->transactional(function () use ($snap, $jobs, $history, $previous, $debut, $lancePar,
            $retentionDays, $presenceDays, $fullDays): int {
            $ids = $this->equipementIds();
            $fin = self::ts($snap['collected_at'] ?? null) ?? self::utc(new \DateTimeImmutable());
            $ko = count(array_filter($jobs, fn(array $j) => $j['statut'] !== 'ok'));
            $statut = $ko === 0 ? 'ok' : ($ko < count($jobs) ? 'partiel' : 'erreur');
            $id = (int) $this->db->fetchOne('INSERT INTO gestion_switch.collecte (debut, fin, statut, lance_par, snapshot)
                VALUES (?::timestamp, ?::timestamp, ?, ?, ?::jsonb) RETURNING id',
                [self::utc($debut), $fin, $statut, self::str($lancePar, 180), self::json($snap)]);

            $results = [];
            foreach ($jobs as $code => $j) {
                if (isset($ids[$code])) {   // GLPI n'est pas un équipement
                    $results[] = ['collecte_id' => $id, 'equipement_id' => $ids[$code], 'statut' => $j['statut'],
                        'duree' => $j['duree'] !== null ? (string) round((float) $j['duree'], 1) : null, 'erreur' => $j['erreur'] ?? null];
                }
            }
            $this->insertRows('collecte_equipement', ['collecte_id' => 'int', 'equipement_id' => 'int', 'statut' => '',
                'duree' => 'numeric', 'erreur' => ''], $results);

            $this->updateEquipements($snap, $ids);
            $this->saveAppareils($history, $previous, $ids);

            $this->saveSummary($id, $snap);
            // reprise d'une collecte plus ancienne que la dernière : l'état actuel n'est pas remplacé
            $latest = !$this->db->fetchOne('SELECT 1 FROM gestion_switch.collecte WHERE debut > ?::timestamp',
                [self::utc($debut)]);
            if ($latest) {
                $this->saveDetail($snap, $ids, $fin);
                $this->savePresence($debut, $snap, $history, $ids, $presenceDays);
            }

            if ($fullDays > 0) {
                // une photo par jour (heure de Paris) : la plus complète, à égalité la dernière de la journée.
                // Les déplacements et les changements de ports restent enregistrés à part.
                $this->db->executeStatement("UPDATE gestion_switch.collecte SET snapshot = NULL
                    WHERE snapshot IS NOT NULL AND debut < ?::timestamp AND id <> ? AND id NOT IN (
                        SELECT DISTINCT ON (jour) id FROM (
                            SELECT id, debut, nb_equipements_ok, (debut AT TIME ZONE 'UTC' AT TIME ZONE 'Europe/Paris')::date AS jour
                            FROM gestion_switch.collecte WHERE snapshot IS NOT NULL
                        ) c ORDER BY jour, nb_equipements_ok DESC NULLS LAST, debut DESC)",
                    [self::utc(new \DateTimeImmutable("-{$fullDays} days")), $id]);
            }
            if ($retentionDays > 0) {
                // la photo la plus récente est toujours gardée
                $this->db->executeStatement('UPDATE gestion_switch.collecte SET snapshot = NULL
                    WHERE snapshot IS NOT NULL AND debut < ?::timestamp AND id <> ?',
                    [self::utc(new \DateTimeImmutable("-{$retentionDays} days")), $id]);
            }
            return $id;
        });
    }

    /** Reprise de l'ancien history.json (aruba:import) : un déplacement initial par appareil. */
    public function importHistory(array $history): int
    {
        return $this->db->transactional(fn() => $this->saveAppareils($history, [], $this->equipementIds()));
    }

    public function countAppareils(): int
    {
        return (int) $this->db->fetchOne('SELECT count(*) FROM gestion_switch.appareil');
    }

    /** Une collecte de cette date est-elle déjà enregistrée ? (reprise de l'ancien snapshot.json) */
    public function hasCollecte(string $collectedAt): bool
    {
        return (bool) $this->db->fetchOne('SELECT 1 FROM gestion_switch.collecte WHERE fin = ?::timestamp',
            [self::ts($collectedAt)]);
    }

    /** code => id */
    private function equipementIds(): array
    {
        return $this->db->fetchAllKeyValue('SELECT code, id FROM gestion_switch.equipement');
    }

    /** Dernière identification connue de chaque switch / cluster (gardée si l'équipement est en erreur). */
    private function updateEquipements(array $snap, array $ids): void
    {
        $items = array_merge(
            array_map(fn(array $s) => [$s['id'], $s['hostname'] ?? null, $s['platform'] ?? null, $s['version'] ?? null,
                $s['system_mac'] ?? null, $s['error'] ?? null, $s['collected_at'] ?? null], $snap['switches'] ?? []),
            array_map(fn(array $w) => [$w['id'], null, null, $w['version'] ?? null, null, $w['error'] ?? null,
                $w['collected_at'] ?? null], $snap['wifi'] ?? []),
        );
        $rows = [];
        foreach ($items as [$code, $hostname, $modele, $version, $mac, $error, $collectedAt]) {
            if (isset($ids[$code])) {
                $rows[] = ['id' => $ids[$code], 'hostname' => self::str($hostname, 100), 'modele' => self::str($modele, 100),
                    'version' => self::str($version, 50), 'mac' => self::mac((string) $mac),
                    'collecte' => $error ? null : self::ts($collectedAt), 'erreur' => $error ? (string) $error : null];
            }
        }
        $this->updateRows(['id' => 'int', 'hostname' => 'text', 'modele' => 'text', 'version' => 'text', 'mac' => 'macaddr',
            'collecte' => 'timestamp', 'erreur' => 'text'], $rows,
            'hostname = COALESCE(v.hostname, e.hostname), modele = COALESCE(v.modele, e.modele),
             version = COALESCE(v.version, e.version), mac_systeme = COALESCE(v.mac, e.mac_systeme),
             derniere_collecte = COALESCE(v.collecte, e.derniere_collecte), derniere_erreur = v.erreur');
        // chiffres de la dernière collecte réussie
        $rows = [];
        foreach ($snap['switches'] ?? [] as $s) {
            if (empty($s['error']) && isset($ids[$s['id']], $s['stats'])) {
                $st = $s['stats'];
                $rows[] = ['id' => $ids[$s['id']], 'total' => $st['ports_total'] ?? null, 'actifs' => $st['ports_up'] ?? null,
                    'mac' => $st['macs'] ?? null, 'voisins' => $st['lldp'] ?? null, 'vlans' => $st['vlans'] ?? null,
                    'api' => self::str($s['api_version'] ?? null, 20)];
            }
        }
        $this->updateRows(['id' => 'int', 'total' => 'int', 'actifs' => 'int', 'mac' => 'int', 'voisins' => 'int',
            'vlans' => 'int', 'api' => 'text'], $rows,
            'ports_total = v.total, ports_actifs = v.actifs, nb_mac = v.mac, nb_voisins = v.voisins, nb_vlans = v.vlans,
             api_detectee = v.api');
    }

    /** UPDATE de plusieurs équipements en une requête : v (colonnes de $types, id en premier) → equipement e. */
    private function updateRows(array $types, array $rows, string $set): void
    {
        if (!$rows) {
            return;
        }
        $this->db->executeStatement("UPDATE gestion_switch.equipement e SET {$set}
            FROM (VALUES " . self::values($types, count($rows)) . ') AS v(' . implode(', ', array_keys($types)) . ')
            WHERE e.id = v.id', self::flatten($rows, array_keys($types)));
    }

    /**
     * Écrit les appareils nouveaux ou modifiés, et un déplacement quand le switch, le port, le VLAN
     * ou la borne Wi-Fi change.
     */
    private function saveAppareils(array $history, array $previous, array $ids): int
    {
        $rows = $moves = [];
        $position = fn(array $h) => [(string) ($h['switch'] ?? ''), (string) ($h['port'] ?? ''),
            (string) ($h['vlan'] ?? ''), (string) ($h['ap'] ?? '')];
        foreach ($history as $mac => $h) {
            $mac = self::mac((string) $mac);
            if ($mac === null || empty($h['last_seen'])) {
                continue;
            }
            $old = $previous[$mac] ?? null;
            if ($old == $h) {
                continue;   // vu dans aucune des dernières collectes et sans changement
            }
            $eq = $ids[$h['switch'] ?? ''] ?? null;
            $ip = self::ip($h['ip'] ?? null);
            $seen = self::ts($h['last_seen']);
            // chaînes vides gardées telles quelles : relues à l'identique par loadHistory()
            $rows[] = [
                'mac' => $mac, 'premiere_vue' => self::ts($h['first_seen'] ?? null) ?? $seen, 'derniere_vue' => $seen,
                'ip' => $ip, 'nom' => self::str($h['name'] ?? null, 255, false),
                'constructeur' => self::str(Inventory::vendor($mac), 100), 'glpi_nom' => self::str($h['glpi_name'] ?? null, 255, false),
                'equipement_id' => $eq, 'port' => self::str($h['port'] ?? null, 30, false),
                'vlan' => self::str($h['vlan'] ?? null, 10, false), 'role' => self::str($h['role'] ?? null, 20, false),
                'borne' => self::str($h['ap'] ?? null, 100, false), 'essid' => self::str($h['essid'] ?? null, 100, false),
            ];
            if ($old === null || $position($old) !== $position($h)) {
                $moves[] = [
                    'mac' => $mac, 'vu_le' => $seen, 'equipement_id' => $eq, 'port' => self::str($h['port'] ?? null, 30, false),
                    'vlan' => self::str($h['vlan'] ?? null, 10, false), 'ip' => $ip,
                    'borne' => self::str($h['ap'] ?? null, 100), 'essid' => self::str($h['essid'] ?? null, 100),
                    'role' => self::str($h['role'] ?? null, 20),
                    'nature' => $old === null ? 'arrivee' : (($h['ap'] ?? '') !== '' ? 'wifi' : 'filaire'),
                ];
            }
        }

        $this->insertRows('appareil AS a', ['mac' => 'macaddr', 'premiere_vue' => 'timestamp', 'derniere_vue' => 'timestamp',
            'ip' => 'inet', 'nom' => '', 'constructeur' => '', 'glpi_nom' => '', 'equipement_id' => 'int', 'port' => '',
            'vlan' => '', 'role' => '', 'borne' => '', 'essid' => ''], $rows,
            'ON CONFLICT (mac) DO UPDATE SET premiere_vue = EXCLUDED.premiere_vue, derniere_vue = EXCLUDED.derniere_vue,
                ip = EXCLUDED.ip, nom = EXCLUDED.nom, constructeur = COALESCE(EXCLUDED.constructeur, a.constructeur),
                glpi_nom = EXCLUDED.glpi_nom, equipement_id = EXCLUDED.equipement_id, port = EXCLUDED.port,
                vlan = EXCLUDED.vlan, role = EXCLUDED.role, borne = EXCLUDED.borne, essid = EXCLUDED.essid');
        $this->insertRows('appareil_mouvement', ['mac' => 'macaddr', 'vu_le' => 'timestamp', 'equipement_id' => 'int',
            'port' => '', 'vlan' => '', 'ip' => 'inet', 'borne' => '', 'essid' => '', 'role' => '', 'nature' => ''], $moves);
        return count($rows);
    }

    /** Résumé de la collecte (évolution du réseau dans le temps sans relire la photo). */
    private function saveSummary(int $id, array $snap): void
    {
        $sm = $snap['summary'] ?? [];
        $all = array_merge($snap['switches'] ?? [], $snap['wifi'] ?? []);
        $this->db->executeStatement('UPDATE gestion_switch.collecte SET duree = EXTRACT(EPOCH FROM (fin - debut))::int,
                nb_equipements = ?::int, nb_equipements_ok = ?::int, nb_mac_uniques = ?::int, ports_actifs = ?::int,
                ports_total = ?::int, nb_bornes = ?::int, nb_clients_wifi = ?::int, nb_glpi = ?::int
            WHERE id = ?', [count($all), count(array_filter($all, fn(array $s) => empty($s['error']))),
            $sm['macs_unique'] ?? null, $sm['ports_up'] ?? null, $sm['ports_total'] ?? null, $sm['wifi_aps'] ?? null,
            $sm['wifi_clients'] ?? null, $sm['glpi_matched'] ?? null, $id]);
    }

    /** État actuel détaillé, pour chaque équipement collecté sans erreur (les autres gardent l'état précédent). */
    private function saveDetail(array $snap, array $ids, string $now): void
    {
        $ok = $ports = $vlans = [];
        foreach ($snap['switches'] ?? [] as $s) {
            if (empty($s['error']) && isset($ids[$s['id']])) {
                $eq = $ok[$s['id']] = $ids[$s['id']];
                $ports[$eq] = $s['ports'] ?? [];
                foreach ($s['vlans'] ?? [] as $v) {
                    $vlans["{$eq}|{$v['id']}"] = ['equipement_id' => $eq, 'vlan' => self::str($v['id'], 10, false),
                        'nom' => self::str($v['name'] ?? null, 100)];
                }
            }
        }

        if ($ok) {
            $eqs = self::pgArray(array_values($ok));
            // VLAN (absents des photos d'avant le 29/10/2026)
            if ($vlans) {
                $this->db->executeStatement('DELETE FROM gestion_switch.vlan WHERE equipement_id = ANY(?::int[])', [$eqs]);
                $this->insertRows('vlan', ['equipement_id' => 'int', 'vlan' => '', 'nom' => ''], array_values($vlans));
            }
            $this->savePorts($ports, $now);

            // tables MAC
            $this->db->executeStatement('DELETE FROM gestion_switch.mac_vue WHERE equipement_id = ANY(?::int[])', [$eqs]);
            $macs = [];
            foreach ($snap['macs'] ?? [] as $m) {
                $mac = self::mac((string) ($m['mac'] ?? ''));
                if ($mac === null || !isset($ok[$m['switch'] ?? ''])) {
                    continue;
                }
                $key = "{$m['switch']}|{$m['port']}|{$mac}|{$m['vlan']}";
                $macs[$key] = ['equipement_id' => $ok[$m['switch']], 'port' => self::str($m['port'], 30, false), 'mac' => $mac,
                    'vlan' => self::str($m['vlan'] ?? '', 10, false), 'role' => self::str($m['role'] ?? null, 10),
                    'type' => self::str($m['type'] ?? null, 10), 'ip' => self::str($m['ip'] ?? null, 100),
                    'via' => self::str($m['via'] ?? null, 100), 'principal' => self::bool(!empty($m['primary'])), 'vu_le' => $now];
            }
            $this->insertRows('mac_vue', ['equipement_id' => 'int', 'port' => '', 'mac' => 'macaddr', 'vlan' => '', 'role' => '',
                'type' => '', 'ip' => '', 'via' => '', 'principal' => 'boolean', 'vu_le' => 'timestamp'], array_values($macs));
        }

        foreach ($snap['wifi'] ?? [] as $w) {
            if (empty($w['error']) && isset($ids[$w['id']])) {
                $this->saveWifi($ids[$w['id']], $w, $ids, $now);
            }
        }
        if (!empty($snap['glpi']) && empty($snap['glpi']['error'])) {
            $this->saveGlpi($snap['glpi']['macs'] ?? [], $now);
        }
    }

    /**
     * Ports et voisins LLDP des switchs collectés ($switches : id de l'équipement => ports), avec un événement
     * pour chaque changement depuis la collecte précédente. Tous les switchs en quelques requêtes.
     */
    private function savePorts(array $switches, string $now): void
    {
        $eqs = self::pgArray(array_keys($switches));
        $old = [];   // "équipement|port" => port enregistré + ses voisins
        foreach ($this->db->fetchAllAssociative('SELECT id, equipement_id, nom, admin, lien, vitesse, description, vlan_mode,
                vlan_natif, vlans_tagues FROM gestion_switch.port WHERE equipement_id = ANY(?::int[])', [$eqs]) as $r) {
            $old["{$r['equipement_id']}|{$r['nom']}"] = $r + ['voisins' => []];
        }
        $byId = [];
        foreach ($old as $key => $r) {
            $byId[$r['id']] = $key;
        }
        foreach ($this->db->fetchAllAssociative('SELECT v.port_id, COALESCE(NULLIF(v.nom, \'\'), v.chassis_id) AS label
                FROM gestion_switch.port_voisin v JOIN gestion_switch.port p ON p.id = v.port_id
                WHERE p.equipement_id = ANY(?::int[])', [$eqs]) as $r) {
            $old[$byId[$r['port_id']]]['voisins'][] = (string) $r['label'];
        }
        // première collecte détaillée d'un switch : pas d'événements
        $known = array_flip(array_map(fn(array $r) => (int) $r['equipement_id'], $old));
        $vlanText = fn($mode, $natif, $tagues) => trim("{$mode} {$natif}" . ($tagues !== '' && $tagues !== null ? " + {$tagues}" : ''));
        $voisinsText = function (array $labels): string {
            sort($labels);
            return implode(', ', $labels);
        };

        $rows = $events = $voisins = [];
        $event = function (int $eq, string $port, string $champ, mixed $avant, mixed $apres) use ($now, &$events) {
            $events[] = ['equipement_id' => $eq, 'port' => self::str($port, 30, false), 'vu_le' => $now, 'champ' => $champ,
                'avant' => $avant === null ? null : (string) $avant, 'apres' => $apres === null ? null : (string) $apres];
        };
        foreach ($switches as $eq => $ports) {
            foreach ($ports as $p) {
                $nom = (string) self::str($p['name'], 30, false);
                $key = "{$eq}|{$nom}";
                $tagues = implode(',', array_map('strval', $p['trunks'] ?? []));
                $speed = is_numeric($p['speed'] ?? null) ? (int) $p['speed'] : null;
                $description = (string) self::str($p['description'] ?? '', 255, false);
                $labels = array_map(fn(array $n) => (string) (($n['name'] ?? '') ?: ($n['chassis_id'] ?? '')), $p['lldp'] ?? []);
                $o = $old[$key] ?? null;
                if ($o !== null) {
                    if ($o['lien'] !== $p['link']) {
                        $event($eq, $nom, 'lien', $o['lien'], $p['link']);
                    }
                    if ($o['admin'] !== $p['admin']) {
                        $event($eq, $nom, 'admin', $o['admin'], $p['admin']);
                    }
                    if ($o['lien'] === 'up' && $p['link'] === 'up' && (int) $o['vitesse'] !== (int) $speed) {
                        $event($eq, $nom, 'vitesse', $o['vitesse'], $speed);
                    }
                    if ((string) $o['description'] !== $description) {
                        $event($eq, $nom, 'description', $o['description'], $description);
                    }
                    $va = $vlanText($o['vlan_mode'], $o['vlan_natif'], $o['vlans_tagues']);
                    $vb = $vlanText($p['vlan_mode'] ?? '', $p['native_vlan'] ?? '', $tagues);
                    if ($va !== $vb) {
                        $event($eq, $nom, 'vlan', $va, $vb);
                    }
                    if ($voisinsText($o['voisins']) !== $voisinsText($labels)) {
                        $event($eq, $nom, 'voisin', $voisinsText($o['voisins']), $voisinsText($labels));
                    }
                } elseif (isset($known[$eq])) {
                    $event($eq, $nom, 'apparu', null, null);
                }
                $rows[$key] = [
                    'equipement_id' => $eq, 'nom' => $nom, 'type' => self::str($p['type'] ?? null, 10),
                    'description' => $description, 'admin' => self::str($p['admin'] ?? null, 10),
                    'lien' => self::str($p['link'] ?? null, 10), 'vitesse' => $speed, 'duplex' => self::str($p['duplex'] ?? null, 10),
                    'connecteur' => self::str($p['connector'] ?? null, 10), 'module' => self::str($p['module'] ?? null, 100),
                    'vlan_mode' => self::str($p['vlan_mode'] ?? null, 30, false), 'vlan_natif' => self::str($p['native_vlan'] ?? null, 10, false),
                    'vlans_tagues' => $tagues, 'role' => self::str($p['role'] ?? null, 10), 'agregat' => self::str($p['lag'] ?? null, 30),
                    'membres' => self::str(implode(',', $p['members'] ?? []), 10000), 'nb_mac' => (int) ($p['mac_count'] ?? 0),
                    'etat_depuis' => $now, 'vu_le' => $now,
                ];
                foreach ($p['lldp'] ?? [] as $n) {
                    $voisins[] = [$key, [
                        'chassis_id' => self::str($n['chassis_id'] ?? null, 50), 'port_distant' => self::str($n['port_id'] ?? null, 100),
                        'nom' => self::str($n['name'] ?? null, 255), 'description' => self::str($n['description'] ?? null, 10000),
                        'description_port' => self::str($n['port_description'] ?? null, 255),
                        'ip_gestion' => self::str($n['mgmt_ip'] ?? null, 50), 'capacites' => self::str($n['capabilities'] ?? null, 100),
                    ]];
                }
            }
        }
        foreach ($old as $key => $o) {
            if (!isset($rows[$key])) {
                $event((int) $o['equipement_id'], $o['nom'], 'retire', null, null);
            }
        }

        $types = ['equipement_id' => 'int', 'nom' => '', 'type' => '', 'description' => '', 'admin' => '', 'lien' => '',
            'vitesse' => 'bigint', 'duplex' => '', 'connecteur' => '', 'module' => '', 'vlan_mode' => '', 'vlan_natif' => '',
            'vlans_tagues' => '', 'role' => '', 'agregat' => '', 'membres' => '', 'nb_mac' => 'int', 'etat_depuis' => 'timestamp',
            'vu_le' => 'timestamp'];
        $set = implode(', ', array_map(fn($c) => "{$c} = EXCLUDED.{$c}",
            array_diff(array_keys($types), ['equipement_id', 'nom', 'etat_depuis'])));
        $portIds = [];
        foreach ($this->insertRows('port AS p', $types, array_values($rows), "ON CONFLICT (equipement_id, nom) DO UPDATE SET {$set},
                etat_depuis = CASE WHEN p.lien IS DISTINCT FROM EXCLUDED.lien OR p.etat_depuis IS NULL
                    THEN EXCLUDED.etat_depuis ELSE p.etat_depuis END
                RETURNING id, equipement_id, nom") as $r) {
            $portIds["{$r['equipement_id']}|{$r['nom']}"] = (int) $r['id'];
        }
        // ports disparus (module retiré, pile réduite…)
        $this->db->executeStatement('DELETE FROM gestion_switch.port WHERE equipement_id = ANY(?::int[])
            AND NOT (id = ANY(?::int[]))', [$eqs, self::pgArray(array_values($portIds))]);

        $this->db->executeStatement('DELETE FROM gestion_switch.port_voisin WHERE port_id IN
            (SELECT id FROM gestion_switch.port WHERE equipement_id = ANY(?::int[]))', [$eqs]);
        $this->insertRows('port_voisin', ['port_id' => 'int', 'chassis_id' => '', 'port_distant' => '', 'nom' => '',
            'description' => '', 'description_port' => '', 'ip_gestion' => '', 'capacites' => ''],
            array_map(fn(array $v) => ['port_id' => $portIds[$v[0]]] + $v[1],
                array_values(array_filter($voisins, fn(array $v) => isset($portIds[$v[0]])))));
        // voisins qui sont des switchs gérés
        $this->db->executeStatement("UPDATE gestion_switch.port_voisin v SET equipement_voisin_id = e.id
            FROM gestion_switch.port p, gestion_switch.equipement e
            WHERE p.id = v.port_id AND p.equipement_id = ANY(?::int[]) AND e.id <> p.equipement_id
              AND (lower(v.chassis_id) = e.mac_systeme::text OR lower(v.nom) = lower(e.hostname) OR v.ip_gestion = e.hote)", [$eqs]);

        $this->insertRows('port_evenement', ['equipement_id' => 'int', 'port' => '', 'vu_le' => 'timestamp', 'champ' => '',
            'avant' => '', 'apres' => ''], $events);
    }

    /** Bornes (gardées quand elles disparaissent) et clients d'un cluster Wi-Fi. */
    private function saveWifi(int $eq, array $w, array $ids, string $now): void
    {
        $aps = [];
        foreach ($w['aps'] ?? [] as $a) {
            $aps[(string) $a['name']] = [
                'equipement_id' => $eq, 'nom' => self::str($a['name'], 100, false), 'ip' => self::ip($a['ip'] ?? null),
                'modele' => self::str($a['model'] ?? null, 50), 'numero_serie' => self::str($a['serial'] ?? null, 50),
                'mode' => self::str($a['mode'] ?? null, 20), 'maitre' => self::bool(!empty($a['conductor'])),
                'nb_clients' => isset($a['clients']) ? (int) $a['clients'] : null,
                'canaux' => self::str(implode(' / ', (array) ($a['channels'] ?? [])), 50),
                'demarree_le' => self::bootTime($a['uptime'] ?? '', $now), 'liaison' => self::str($a['uplink'] ?? null, 20),
                'switch_id' => $ids[$a['switch'] ?? ''] ?? null, 'port' => self::str($a['port'] ?? null, 30),
                'premiere_vue' => $now, 'derniere_vue' => $now,
            ];
        }
        $this->insertRows('borne_wifi', ['equipement_id' => 'int', 'nom' => '', 'ip' => 'inet', 'modele' => '', 'numero_serie' => '',
            'mode' => '', 'maitre' => 'boolean', 'nb_clients' => 'int', 'canaux' => '', 'demarree_le' => 'timestamp', 'liaison' => '',
            'switch_id' => 'int', 'port' => '', 'premiere_vue' => 'timestamp', 'derniere_vue' => 'timestamp'], array_values($aps),
            'ON CONFLICT (equipement_id, nom) DO UPDATE SET ip = EXCLUDED.ip, modele = EXCLUDED.modele,
                numero_serie = EXCLUDED.numero_serie, mode = EXCLUDED.mode, maitre = EXCLUDED.maitre,
                nb_clients = EXCLUDED.nb_clients, canaux = EXCLUDED.canaux, demarree_le = EXCLUDED.demarree_le,
                liaison = EXCLUDED.liaison, switch_id = EXCLUDED.switch_id, port = EXCLUDED.port,
                derniere_vue = EXCLUDED.derniere_vue');

        $this->db->executeStatement('DELETE FROM gestion_switch.client_wifi WHERE equipement_id = ?', [$eq]);
        $clients = [];
        foreach ($w['clients'] ?? [] as $c) {
            $mac = self::mac((string) ($c['mac'] ?? ''));
            if ($mac === null) {
                continue;
            }
            $clients[$mac] = [
                'mac' => $mac, 'equipement_id' => $eq, 'borne' => self::str($c['ap'] ?? null, 100),
                'nom' => self::str($c['name'] ?? null, 255), 'ip' => self::ip($c['ip'] ?? null),
                'systeme' => self::str($c['os'] ?? null, 50), 'essid' => self::str($c['essid'] ?? null, 100),
                'role' => self::str($c['role'] ?? null, 100), 'canal' => self::str($c['channel'] ?? null, 10),
                'bande' => self::str($c['band'] ?? null, 20), 'norme' => self::str($c['standard'] ?? null, 20),
                'signal' => is_numeric($c['signal'] ?? null) ? (int) $c['signal'] : null,
                'qualite' => self::str($c['signal_quality'] ?? null, 10),
                'debit' => is_numeric($c['speed'] ?? null) ? (int) $c['speed'] : null, 'vu_le' => $now,
            ];
        }
        $types = ['mac' => 'macaddr', 'equipement_id' => 'int', 'borne' => '', 'nom' => '', 'ip' => 'inet', 'systeme' => '',
            'essid' => '', 'role' => '', 'canal' => '', 'bande' => '', 'norme' => '', 'signal' => 'int', 'qualite' => '',
            'debit' => 'int', 'vu_le' => 'timestamp'];
        $set = implode(', ', array_map(fn($c) => "{$c} = EXCLUDED.{$c}", array_diff(array_keys($types), ['mac'])));
        $this->insertRows('client_wifi', $types, array_values($clients), "ON CONFLICT (mac) DO UPDATE SET {$set}");
        // système d'exploitation de l'appareil
        $this->db->executeStatement("UPDATE gestion_switch.appareil a SET systeme = c.systeme
            FROM gestion_switch.client_wifi c
            WHERE c.mac = a.mac AND c.equipement_id = ? AND COALESCE(c.systeme, '') <> ''", [$eq]);
    }

    /** Fiches GLPI des appareils du réseau (remplacées à chaque collecte GLPI réussie). */
    private function saveGlpi(array $macs, string $now): void
    {
        $this->db->executeStatement('DELETE FROM gestion_switch.glpi_fiche');
        $rows = [];
        foreach ($macs as $mac => $g) {
            $mac = self::mac((string) $mac);
            if ($mac === null) {
                continue;
            }
            $rows[$mac] = [
                'mac' => $mac, 'itemtype' => self::str($g['itemtype'] ?? null, 50),
                'glpi_id' => is_numeric($g['id'] ?? null) ? (int) $g['id'] : null,
                'type_libelle' => self::str($g['type_label'] ?? null, 50), 'nom' => self::str($g['name'] ?? null, 255),
                'genre' => self::str($g['kind'] ?? null, 100), 'modele' => self::str($g['model'] ?? null, 255),
                'numero_serie' => self::str($g['serial'] ?? null, 100), 'utilisateur' => self::str($g['user'] ?? null, 255),
                'contact' => self::str($g['contact'] ?? null, 255), 'lieu' => self::str($g['location'] ?? null, 255),
                'statut' => self::str($g['status'] ?? null, 50), 'inventaire' => self::str($g['inventory'] ?? null, 100),
                'port' => self::str($g['port'] ?? null, 100), 'url' => self::str($g['url'] ?? null, 500), 'vu_le' => $now,
            ];
        }
        $this->insertRows('glpi_fiche', ['mac' => 'macaddr', 'itemtype' => '', 'glpi_id' => 'int', 'type_libelle' => '', 'nom' => '',
            'genre' => '', 'modele' => '', 'numero_serie' => '', 'utilisateur' => '', 'contact' => '', 'lieu' => '', 'statut' => '',
            'inventaire' => '', 'port' => '', 'url' => '', 'vu_le' => 'timestamp'], array_values($rows));
    }

    /**
     * Position de chaque appareil vu par cette collecte, par périodes : la période de l'appareil est prolongée
     * s'il était au même endroit à la collecte précédente, sinon une nouvelle commence.
     */
    private function savePresence(\DateTimeImmutable $debut, array $snap, array $history, array $ids, int $presenceDays): void
    {
        $now = $snap['collected_at'] ?? null;
        if ($now === null) {
            return;
        }
        $signal = [];
        foreach ($snap['wifi'] ?? [] as $w) {
            foreach ($w['clients'] ?? [] as $c) {
                $signal[$c['mac']] = is_numeric($c['signal'] ?? null) ? (int) $c['signal'] : null;
            }
        }
        $rows = [];
        foreach ($history as $mac => $h) {
            $mac = self::mac((string) $mac);
            if ($mac === null || ($h['last_seen'] ?? null) !== $now) {
                continue;
            }
            $rows[] = [
                'mac' => $mac, 'equipement_id' => $ids[$h['switch'] ?? ''] ?? null,
                'port' => self::str($h['port'] ?? null, 30), 'vlan' => self::str($h['vlan'] ?? null, 10),
                'ip' => self::ip($h['ip'] ?? null), 'role' => self::str($h['role'] ?? null, 20),
                'borne' => self::str($h['ap'] ?? null, 100), 'essid' => self::str($h['essid'] ?? null, 100),
                'signal' => $signal[$mac] ?? null,
            ];
        }

        $types = ['mac' => 'macaddr', 'equipement_id' => 'int', 'port' => 'text', 'vlan' => 'text', 'ip' => 'inet',
            'role' => 'text', 'borne' => 'text', 'essid' => 'text', 'signal' => 'int'];
        $cols = array_keys($types);
        $position = ['equipement_id', 'port', 'vlan', 'ip', 'role', 'borne', 'essid'];
        $sql = 'WITH n (' . implode(', ', $cols) . ') AS (VALUES %s),
            prolongees AS (
                UPDATE gestion_switch.appareil_presence p SET fin = ?::timestamp,
                    signal_min = LEAST(p.signal_min, n.signal), signal_max = GREATEST(p.signal_max, n.signal)
                FROM n WHERE p.fin = ?::timestamp AND p.mac = n.mac AND '
                . implode(' AND ', array_map(fn(string $c) => "p.{$c} IS NOT DISTINCT FROM n.{$c}", $position)) . '
                RETURNING p.mac)
            INSERT INTO gestion_switch.appareil_presence (mac, debut, fin, ' . implode(', ', $position) . ', signal_min, signal_max)
            SELECT n.mac, ?::timestamp, ?::timestamp, ' . implode(', ', array_map(fn(string $c) => "n.{$c}", $position)) . ', n.signal, n.signal
            FROM n WHERE n.mac NOT IN (SELECT mac FROM prolongees)';
        $t = self::utc($debut);
        $precedente = $this->db->fetchOne('SELECT max(debut) FROM gestion_switch.collecte WHERE debut < ?::timestamp', [$t]);
        foreach (array_chunk($rows, intdiv(20000, count($cols))) as $chunk) {
            $this->db->executeStatement(sprintf($sql, self::values($types, count($chunk))),
                [...self::flatten($chunk, $cols), $t, $precedente, $t, $t]);
        }

        if ($presenceDays > 0) {
            $this->db->executeStatement('DELETE FROM gestion_switch.appareil_presence WHERE fin < ?::timestamp',
                [self::utc(new \DateTimeImmutable("-{$presenceDays} days"))]);
        }
    }

    /**
     * INSERT multi-lignes, par paquets d'environ 20 000 paramètres : le serveur étant distant, le temps
     * d'enregistrement dépend surtout du nombre de requêtes.
     * $types : colonne => conversion PostgreSQL ('' : texte), $rows : lignes indexées par colonne,
     * $suffix : ON CONFLICT…, RETURNING… (les lignes renvoyées sont retournées).
     */
    private function insertRows(string $table, array $types, array $rows, string $suffix = ''): array
    {
        $cols = array_keys($types);
        $out = [];
        foreach (array_chunk($rows, max(1, intdiv(20000, count($cols)))) as $chunk) {
            $sql = "INSERT INTO gestion_switch.{$table} (" . implode(', ', $cols) . ') VALUES '
                . self::values($types, count($chunk)) . ' ' . $suffix;
            if (str_contains($suffix, 'RETURNING')) {
                array_push($out, ...$this->db->fetchAllAssociative($sql, self::flatten($chunk, $cols)));
            } else {
                $this->db->executeStatement($sql, self::flatten($chunk, $cols));
            }
        }
        return $out;
    }

    /** « (?::int, ?, …), (…) » pour $n lignes */
    private static function values(array $types, int $n): string
    {
        $row = '(' . implode(', ', array_map(fn($t) => $t === '' ? '?' : "?::{$t}", $types)) . ')';
        return implode(', ', array_fill(0, $n, $row));
    }

    /** Paramètres des lignes, dans l'ordre des colonnes */
    private static function flatten(array $rows, array $cols): array
    {
        $params = [];
        foreach ($rows as $row) {
            foreach ($cols as $c) {
                $params[] = $row[$c] ?? null;
            }
        }
        return $params;
    }

    // ════════════════════════════════════════════════════════════ conversions
    private static function historyEntry(array $r): array
    {
        $h = [
            'first_seen' => self::iso($r['premiere_vue']),
            'last_seen' => self::iso($r['derniere_vue']),
            'switch' => (string) $r['code'],
            'switch_name' => self::switchName($r),
            'port' => (string) $r['port'],
        ];
        foreach (['vlan' => 'vlan', 'role' => 'role', 'ip' => 'ip', 'name' => 'nom', 'ap' => 'borne', 'essid' => 'essid',
                     'glpi_name' => 'glpi_nom'] as $key => $col) {
            if ($r[$col] !== null) {
                $h[$key] = $r[$col];
            }
        }
        return $h;
    }

    /** Même nom que dans la photo du réseau (Inventory::build) : nom saisi, sinon hostname, sinon adresse. */
    private static function switchName(array $r, string $prefix = 'e_', string $code = 'code'): string
    {
        return $r[$code] === null ? ''
            : (string) (($r[$prefix . 'nom'] ?? '') ?: (($r[$prefix . 'hostname'] ?? '') ?: $r[$prefix . 'hote']));
    }

    /** « aa:bb:cc:dd:ee:ff » quel que soit le format d'origine (aabbcc-ddeeff…), null si ce n'est pas une MAC. */
    private static function mac(string $mac): ?string
    {
        $hex = strtolower((string) preg_replace('/[^0-9A-Fa-f]/', '', $mac));
        return strlen($hex) === 12 && preg_match('/^[0-9a-f:.\-\s]+$/i', $mac)
            ? implode(':', str_split($hex, 2)) : null;
    }

    private static function ip(?string $ip): ?string
    {
        return $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private static function bool(bool $b): string
    {
        return $b ? 't' : 'f';
    }

    /** Tableau PostgreSQL littéral : {"a","b"} */
    private static function pgArray(array $values): string
    {
        return '{' . implode(',', array_map(fn($v) => '"' . addcslashes((string) $v, '"\\') . '"', $values)) . '}';
    }

    /** Date de démarrage d'une borne d'après sa durée de fonctionnement (« 91d:0h:47m:58s »). */
    private static function bootTime(string $uptime, string $now): ?string
    {
        if (!preg_match('/^(?:(\d+)d)?:?(?:(\d+)h)?:?(?:(\d+)m)?:?(?:(\d+)s)?$/', trim($uptime), $m) || trim($uptime) === '') {
            return null;
        }
        $seconds = (int) ($m[1] ?? 0) * 86400 + (int) ($m[2] ?? 0) * 3600 + (int) ($m[3] ?? 0) * 60 + (int) ($m[4] ?? 0);
        return self::utc((new \DateTimeImmutable($now, new \DateTimeZone('UTC')))->modify("-{$seconds} seconds"));
    }

    /** Chaîne tronquée à la taille de la colonne ; null si absente (ou vide quand $emptyIsNull). */
    private static function str(mixed $v, int $max, bool $emptyIsNull = true): ?string
    {
        if ($v === null || ($emptyIsNull && $v === '')) {
            return null;
        }
        return mb_substr((string) $v, 0, $max);
    }

    /** Date ISO 8601 (« 2026-10-29T10:42:21+00:00 ») → TIMESTAMP UTC */
    private static function ts(?string $iso): ?string
    {
        return $iso === null || $iso === '' ? null : self::utc(new \DateTimeImmutable($iso));
    }

    private static function utc(\DateTimeImmutable $d): string
    {
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** TIMESTAMP UTC → date ISO 8601, au format écrit par Inventory::build */
    private static function iso(?string $ts): ?string
    {
        return $ts === null ? null : (new \DateTimeImmutable($ts, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:sP');
    }

    private static function json(array $data): string
    {
        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        // jsonb refuse le caractère nul (parfois présent dans les noms annoncés en LLDP)
        return (string) preg_replace('/(?<!\\\\)\\\\u0000/', '', $json);
    }
}
