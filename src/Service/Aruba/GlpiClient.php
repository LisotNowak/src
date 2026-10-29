<?php
/**
 * Client minimal (lecture seule) pour l'API High-Level de GLPI 11 (api.php/v3.0).
 *
 * Authentification OAuth2 « password » : client OAuth (Configuration > Clients OAuth, portées api et graphql)
 * + compte GLPI en lecture seule. Les ports réseau (qui portent les adresses MAC) n'ont pas de route REST de
 * liste : ils sont lus en GraphQL, comme les éléments du parc auxquels ils appartiennent.
 */
declare(strict_types=1);

namespace App\Service\Aruba;

class GlpiClient
{
    /** Éléments du parc qui peuvent avoir des ports réseau => libellé */
    public const TYPES = [
        'Computer' => 'Ordinateur',
        'Printer' => 'Imprimante',
        'NetworkEquipment' => 'Matériel réseau',
        'Phone' => 'Téléphone',
        'Peripheral' => 'Périphérique',
    ];
    private const PAGE = 1000;

    private string $base;
    private ?string $token = null;
    public string $apiVersion = 'GLPI API v3.0';

    public function __construct(
        private string $url,
        private string $clientId,
        private string $clientSecret,
        private string $username,
        private string $password,
        private int $timeout = 30,
    ) {
        $this->url = rtrim(preg_replace('#/api\.php.*$#', '', $url), '/');
        $this->base = $this->url . '/api.php';
    }

    /** @return array{0:int,1:string} */
    private function request(string $method, string $url, array $headers = [], ?array $json = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout * 4,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
            CURLOPT_POSTFIELDS => $json === null ? null : json_encode($json),
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            throw new AosCxException(sprintf('Connexion à GLPI impossible : %s', curl_error($ch)));
        }
        return [(int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string) $body];
    }

    public function login(): void
    {
        [$code, $body] = $this->request('POST', "{$this->base}/token", [], [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->username,
            'password' => $this->password,
            'scope' => 'api graphql',
        ]);
        $data = json_decode($body, true);
        if ($code !== 200 || empty($data['access_token'])) {
            $err = (string) ($data['error'] ?? '');
            throw new AosCxException(match ($err) {
                'invalid_client' => 'GLPI : identifiant ou secret du client OAuth refusé',
                'invalid_grant' => 'GLPI : identifiant ou mot de passe du compte refusé',
                'unauthorized_client' => 'GLPI : le client OAuth n\'autorise pas le type « Password »',
                'invalid_scope' => 'GLPI : le client OAuth doit avoir les portées « api » et « graphql »',
                default => sprintf('GLPI : connexion refusée (%d) %s', $code, mb_substr((string) ($data['error_description'] ?? strip_tags($body)), 0, 200)),
            });
        }
        $this->token = $data['access_token'];
    }

    /** Requête GraphQL, retourne "data". */
    public function graphql(string $query): array
    {
        [$code, $body] = $this->request('POST', "{$this->base}/v3.0/GraphQL/", ["Authorization: Bearer {$this->token}"],
            ['query' => $query]);
        $data = json_decode($body, true);
        if ($code !== 200 || !is_array($data) || !empty($data['errors'])) {
            throw new AosCxException('GLPI : requête refusée (' . $code . ') '
                . mb_substr((string) ($data['errors'][0]['message'] ?? $data['detail'] ?? strip_tags($body)), 0, 200));
        }
        return $data['data'] ?? [];
    }

    /** Tous les éléments d'un type, page par page. */
    private function all(string $type, string $fields): array
    {
        $out = [];
        for ($start = 0; ; $start += self::PAGE) {
            $page = $this->graphql(sprintf('{ %s(limit: %d, start: %d) { %s } }', $type, self::PAGE, $start, $fields))[$type] ?? [];
            array_push($out, ...$page);
            if (count($page) < self::PAGE) {
                return $out;
            }
        }
    }

    /**
     * Lien vers la fiche de l'élément dans GLPI : index.php?redirect=Type_id (format des notifications GLPI).
     * Session GLPI ouverte : renvoi direct sur la fiche ; sinon page de connexion, puis la fiche après une
     * connexion par identifiant GLPI.
     *
     * Le cookie de session GLPI doit être en SameSite=Lax : en Strict il n'est pas envoyé depuis un autre site
     * et GLPI redemande la connexion. Ne pas passer par le callback du plugin singlesignon avec /q/ : l'adresse
     * de retour varie à chaque fiche et Microsoft Entra la refuse (AADSTS50011).
     */
    public function itemUrl(string $itemtype, int $id): string
    {
        return sprintf('%s/index.php?redirect=%s_%d', $this->url, $itemtype, $id);
    }

    /** Test de connexion (écran Configuration) : vérifie aussi que les ports réseau sont lisibles. */
    public function system(): array
    {
        $this->graphql('{ NetworkPort(limit: 1) { id mac } Computer(limit: 1) { id } }');
        return ['hostname' => (string) parse_url($this->url, PHP_URL_HOST), 'platform' => 'GLPI', 'version' => '',
            'system_mac' => ''];
    }

    /**
     * Inventaire indexé par adresse MAC : élément du parc qui possède une carte réseau avec cette adresse.
     * Une même MAC peut figurer sur plusieurs éléments (station d'accueil, adaptateur USB passé d'un PC à
     * l'autre) : on garde l'élément inventorié le plus récemment.
     */
    public function collect(): array
    {
        $assets = [];
        foreach (array_keys(self::TYPES) as $type) {
            $fields = 'id name serial contact is_deleted user { username realname firstname } location { completename }'
                . ' type { name } model { name } manufacturer { name } status { name }'
                . ($type === 'Computer' ? ' last_inventory_update' : '');
            foreach ($this->all($type, $fields) as $a) {
                if (!$a['is_deleted']) {
                    $assets[$type][$a['id']] = $a;
                }
            }
        }
        $ports = $this->all('NetworkPort', 'name mac itemtype items_id is_deleted');
        $macs = [];
        foreach ($ports as $p) {
            $mac = AosCxClient::normMac((string) ($p['mac'] ?? ''));
            $a = $assets[$p['itemtype']][$p['items_id']] ?? null;
            if ($p['is_deleted'] || $a === null || !preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)
                || $mac === '00:00:00:00:00:00') {
                continue;
            }
            $u = $a['user'] ?? null;
            // le nom de modèle remonté par l'agent commence souvent par le fabricant (« HP ZBook… »)
            $maker = (string) ($a['manufacturer']['name'] ?? '');
            $model = (string) ($a['model']['name'] ?? '');
            $item = [
                'itemtype' => $p['itemtype'],
                'type_label' => self::TYPES[$p['itemtype']],
                'id' => $a['id'],
                'name' => (string) $a['name'],
                'kind' => (string) ($a['type']['name'] ?? ''),
                'model' => $maker !== '' && stripos($model, $maker) === 0 ? $model : trim("{$maker} {$model}"),
                'serial' => (string) ($a['serial'] ?? ''),
                'user' => $u ? (trim(($u['firstname'] ?? '') . ' ' . ($u['realname'] ?? '')) ?: (string) $u['username']) : '',
                'contact' => (string) ($a['contact'] ?? ''),
                'location' => (string) ($a['location']['completename'] ?? ''),
                'status' => (string) ($a['status']['name'] ?? ''),
                'inventory' => (string) ($a['last_inventory_update'] ?? ''),
                'port' => (string) ($p['name'] ?? ''),
                'url' => $this->itemUrl($p['itemtype'], (int) $a['id']),
            ];
            $cur = $macs[$mac] ?? null;
            if ($cur === null || $item['inventory'] > $cur['inventory']) {
                $macs[$mac] = $item;
            }
        }
        return ['glpi' => ['url' => $this->url, 'assets' => array_sum(array_map('count', $assets)),
            'macs' => $macs]];
    }

    public function logout(): void
    {
        $this->token = null;
    }
}
