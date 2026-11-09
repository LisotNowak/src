<?php

namespace App\Service\Salles;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Réservation des salles de réunion Outlook (Exchange Online via Microsoft Graph).
 *
 * - Les salles viennent de l'annuaire Exchange (boîtes « salle »), éventuellement
 *   limitées à la liste SALLES_OUTLOOK (adresses séparées par des virgules).
 * - Le planning est lu dans le calendrier de chaque salle.
 * - Une réservation est une réunion créée dans le calendrier Outlook de l'utilisateur
 *   connecté, avec la salle en participant « ressource » : l'utilisateur en est
 *   l'organisateur et la retrouve dans Outlook, la salle l'accepte selon ses règles.
 */
class SalleService
{
    public const TZ = 'Europe/Paris';
    private const MAX_JOURS = 14;
    private const MAX_PARTICIPANTS = 50;

    private readonly \DateTimeZone $tz;
    private readonly \DateTimeZone $utc;

    public function __construct(
        private readonly GraphClient $graph,
        private readonly CacheInterface $cache,
        private readonly string $sallesFiltre = '',
    ) {
        $this->tz = new \DateTimeZone(self::TZ);
        $this->utc = new \DateTimeZone('UTC');
    }

    // ── Salles ────────────────────────────────────────────────────────────

    /** @return list<array{email:string,nom:string,capacite:?int,batiment:?string,etage:?string,equipements:list<string>,accessible:bool}> */
    public function salles(bool $rafraichir = false): array
    {
        $cle = 'salles_outlook_liste_' . md5($this->sallesFiltre);
        if ($rafraichir) {
            $this->cache->delete($cle);
        }

        return $this->cache->get($cle, function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $salles = [];
            foreach ($this->graph->getAll('/places/microsoft.graph.room', ['$top' => 100]) as $p) {
                $email = strtolower((string) ($p['emailAddress'] ?? ''));
                if ($email === '') {
                    continue;
                }
                $salles[$email] = [
                    'email' => $email,
                    'nom' => (string) ($p['displayName'] ?? $email),
                    'capacite' => isset($p['capacity']) ? (int) $p['capacity'] : null,
                    'batiment' => $p['building'] ?? null,
                    'etage' => isset($p['floorLabel']) ? (string) $p['floorLabel'] : (isset($p['floorNumber']) ? (string) $p['floorNumber'] : null),
                    'equipements' => array_values(array_filter([
                        !empty($p['displayDeviceName']) ? 'Écran' : null,
                        !empty($p['videoDeviceName']) ? 'Visio' : null,
                        !empty($p['audioDeviceName']) ? 'Audio' : null,
                    ])),
                    'accessible' => (bool) ($p['isWheelChairAccessible'] ?? false),
                ];
            }

            $filtre = $this->filtre();
            if ($filtre) {
                $liste = [];
                foreach ($filtre as $email) {
                    // Une salle listée mais absente de l'annuaire des salles reste utilisable
                    $liste[$email] = $salles[$email] ?? [
                        'email' => $email, 'nom' => strstr($email, '@', true) ?: $email, 'capacite' => null,
                        'batiment' => null, 'etage' => null, 'equipements' => [], 'accessible' => false,
                    ];
                }
                $salles = $liste;
            }

            $salles = array_values($salles);
            usort($salles, fn (array $a, array $b) => strnatcasecmp($a['nom'], $b['nom']));

            return $salles;
        });
    }

    private function salle(string $email): array
    {
        $email = strtolower(trim($email));
        foreach ($this->salles() as $salle) {
            if ($salle['email'] === $email) {
                return $salle;
            }
        }

        // Garde-fou : le jeton applicatif peut lire n'importe quelle boîte, on se limite aux salles
        throw new GraphException('Salle inconnue.');
    }

    /** @return list<string> */
    private function filtre(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $e) => strtolower(trim($e)),
            explode(',', $this->sallesFiltre),
        ))));
    }

    // ── Planning ──────────────────────────────────────────────────────────

    /**
     * Réservations de toutes les salles (ou d'une seule) entre deux dates.
     * Une salle en erreur (droits, boîte introuvable…) n'empêche pas l'affichage des autres.
     */
    public function planning(\DateTimeImmutable $debut, \DateTimeImmutable $fin, string $moi, ?string $salle = null): array
    {
        if ($fin <= $debut || $debut->diff($fin)->days > self::MAX_JOURS) {
            throw new GraphException('Période invalide (' . self::MAX_JOURS . ' jours maximum).');
        }

        $salles = $salle !== null ? [$this->salle($salle)] : $this->salles();

        // Toutes les requêtes partent en parallèle, les réponses sont lues ensuite
        $reponses = [];
        foreach ($salles as $s) {
            $reponses[$s['email']] = $this->graph->request('GET', '/users/' . rawurlencode($s['email']) . '/calendarView', ['query' => [
                'startDateTime' => $this->versGraph($debut),
                'endDateTime' => $this->versGraph($fin),
                '$select' => 'subject,organizer,start,end,isAllDay,showAs,sensitivity,attendees,bodyPreview,isCancelled,iCalUId,isOnlineMeeting,type',
                '$orderby' => 'start/dateTime',
                '$top' => 500,
            ]]);
        }

        $resultat = [];
        foreach ($salles as $s) {
            $s['reservations'] = [];
            $s['erreur'] = null;
            try {
                foreach ($this->graph->decode($reponses[$s['email']])['value'] ?? [] as $ev) {
                    if (!empty($ev['isCancelled'])) {
                        continue;
                    }
                    $s['reservations'][] = $this->reservation($ev, $s['email'], $moi);
                }
            } catch (\Throwable $e) {
                $s['erreur'] = $e instanceof GraphException ? $e->getMessage() : 'Calendrier illisible.';
            }
            $resultat[] = $s;
        }

        return $resultat;
    }

    private function reservation(array $ev, string $emailSalle, string $moi): array
    {
        $prive = in_array($ev['sensitivity'] ?? 'normal', ['private', 'confidential'], true);
        $organisateur = $ev['organizer']['emailAddress'] ?? [];
        $estMoi = $moi !== '' && strtolower((string) ($organisateur['address'] ?? '')) === strtolower($moi);

        $participants = [];
        if (!$prive) {
            foreach ($ev['attendees'] ?? [] as $a) {
                $adresse = strtolower((string) ($a['emailAddress']['address'] ?? ''));
                if ($adresse === $emailSalle || ($a['type'] ?? '') === 'resource') {
                    continue;
                }
                $participants[] = [
                    'nom' => (string) ($a['emailAddress']['name'] ?? $adresse),
                    'email' => $adresse,
                    'reponse' => (string) ($a['status']['response'] ?? 'none'),
                ];
            }
        }

        return [
            'uid' => (string) ($ev['iCalUId'] ?? ''),
            'sujet' => $prive && !$estMoi ? 'Réservation privée' : (trim((string) ($ev['subject'] ?? '')) ?: '(sans objet)'),
            'prive' => $prive,
            'organisateur' => (string) ($organisateur['name'] ?? $organisateur['address'] ?? ''),
            'organisateurEmail' => (string) ($organisateur['address'] ?? ''),
            'debut' => $this->depuisGraph($ev['start'] ?? [])?->format(DATE_ATOM),
            'fin' => $this->depuisGraph($ev['end'] ?? [])?->format(DATE_ATOM),
            'journee' => (bool) ($ev['isAllDay'] ?? false),
            'statut' => (string) ($ev['showAs'] ?? 'busy'),
            'enLigne' => (bool) ($ev['isOnlineMeeting'] ?? false),
            'apercu' => $prive ? '' : trim((string) ($ev['bodyPreview'] ?? '')),
            'participants' => $participants,
            'recurrent' => ($ev['type'] ?? 'singleInstance') !== 'singleInstance',
            // Annulation possible depuis le portail : réunion simple dont on est l'organisateur
            'annulable' => $estMoi && ($ev['type'] ?? 'singleInstance') === 'singleInstance',
        ];
    }

    // ── Réservation ───────────────────────────────────────────────────────

    /**
     * Crée la réunion dans le calendrier Outlook de l'utilisateur avec la salle en ressource.
     * Les invitations partent automatiquement vers la salle et les participants.
     */
    public function reserver(string $moi, array $donnees): array
    {
        $salle = $this->salle((string) ($donnees['salle'] ?? ''));
        $sujet = trim((string) ($donnees['sujet'] ?? ''));
        $description = trim((string) ($donnees['description'] ?? ''));
        $debut = $this->dateLocale((string) ($donnees['debut'] ?? ''));
        $fin = $this->dateLocale((string) ($donnees['fin'] ?? ''));

        if ($moi === '') {
            throw new GraphException('Votre compte n\'a pas d\'adresse e-mail.');
        }
        if ($sujet === '' || mb_strlen($sujet) > 255) {
            throw new GraphException('L\'objet est obligatoire (255 caractères maximum).');
        }
        if (mb_strlen($description) > 5000) {
            throw new GraphException('Description trop longue (5000 caractères maximum).');
        }
        if (!$debut || !$fin || $fin <= $debut) {
            throw new GraphException('Horaires invalides : la fin doit être après le début.');
        }
        if ($debut < new \DateTimeImmutable('-10 minutes', $this->tz)) {
            throw new GraphException('Impossible de réserver dans le passé.');
        }
        if ($fin->getTimestamp() - $debut->getTimestamp() > 12 * 3600) {
            throw new GraphException('Une réservation ne peut pas dépasser 12 heures.');
        }

        $participants = [];
        foreach (preg_split('/[\s,;]+/', (string) ($donnees['participants'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new GraphException('Adresse de participant invalide : ' . $email);
            }
            $email = strtolower($email);
            if ($email !== strtolower($moi) && $email !== $salle['email']) {
                $participants[$email] = ['emailAddress' => ['address' => $email], 'type' => 'required'];
            }
        }
        if (count($participants) > self::MAX_PARTICIPANTS) {
            throw new GraphException('Trop de participants (' . self::MAX_PARTICIPANTS . ' maximum).');
        }

        // Vérifie que la salle est libre sur le créneau
        foreach ($this->planning($debut, $fin, $moi, $salle['email'])[0]['reservations'] as $r) {
            if ($r['statut'] === 'free') {
                continue;
            }
            $rDebut = new \DateTimeImmutable($r['debut']);
            $rFin = new \DateTimeImmutable($r['fin']);
            if ($rDebut < $fin && $rFin > $debut) {
                throw new GraphException(sprintf(
                    '%s est déjà réservée de %s à %s (%s).',
                    $salle['nom'], $rDebut->setTimezone($this->tz)->format('H:i'), $rFin->setTimezone($this->tz)->format('H:i'), $r['organisateur'] ?: $r['sujet'],
                ));
            }
        }

        try {
            $ev = $this->graph->post('/users/' . rawurlencode($moi) . '/events', [
                'subject' => $sujet,
                'body' => ['contentType' => 'text', 'content' => $description],
                'start' => ['dateTime' => $this->versGraph($debut), 'timeZone' => 'UTC'],
                'end' => ['dateTime' => $this->versGraph($fin), 'timeZone' => 'UTC'],
                'location' => [
                    'displayName' => $salle['nom'],
                    'locationEmailAddress' => $salle['email'],
                    'locationType' => 'conferenceRoom',
                ],
                'attendees' => [
                    ['emailAddress' => ['address' => $salle['email'], 'name' => $salle['nom']], 'type' => 'resource'],
                    ...array_values($participants),
                ],
                'showAs' => 'busy',
            ]);
        } catch (GraphException $e) {
            if ($e->getCode() === 404) {
                throw new GraphException('Aucune boîte Outlook trouvée pour ' . $moi . ' : seuls les comptes Microsoft de l\'entreprise peuvent réserver.');
            }
            throw $e;
        }

        return ['uid' => $ev['iCalUId'] ?? null, 'salle' => $salle['nom']];
    }

    /** Annule (avec message aux participants et à la salle) une réunion dont l'utilisateur est l'organisateur. */
    public function annuler(string $moi, string $uid, string $commentaire = ''): void
    {
        if ($moi === '' || $uid === '' || strlen($uid) > 1024) {
            throw new GraphException('Réservation introuvable.');
        }

        $evenements = $this->graph->get('/users/' . rawurlencode($moi) . '/events', [
            '$filter' => "iCalUId eq '" . str_replace("'", "''", $uid) . "'",
            '$select' => 'id,isOrganizer,type',
        ])['value'] ?? [];

        $ev = $evenements[0] ?? null;
        if (!$ev || empty($ev['isOrganizer'])) {
            throw new GraphException('Seul l\'organisateur peut annuler cette réservation.');
        }

        $this->graph->post('/users/' . rawurlencode($moi) . '/events/' . rawurlencode($ev['id']) . '/cancel', [
            'comment' => mb_substr(trim($commentaire), 0, 1000) ?: 'Réservation annulée depuis le portail.',
        ]);
    }

    // ── Dates ─────────────────────────────────────────────────────────────

    /** « 2026-11-09T14:30 » (heure de Paris) → date, ou null si le format est faux */
    public function dateLocale(string $valeur): ?\DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $valeur, $this->tz) ?: null;
    }

    public function jour(string $valeur): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur, $this->tz) ?: new \DateTimeImmutable('today', $this->tz);
    }

    private function versGraph(\DateTimeImmutable $d): string
    {
        return $d->setTimezone($this->utc)->format('Y-m-d\TH:i:s');
    }

    private function depuisGraph(array $dt): ?\DateTimeImmutable
    {
        if (empty($dt['dateTime'])) {
            return null;
        }
        // Graph renvoie 7 décimales (« 2026-11-09T08:00:00.0000000 ») : on garde la seconde
        $d = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', substr($dt['dateTime'], 0, 19), $this->utc);

        return $d ? $d->setTimezone($this->tz) : null;
    }
}
