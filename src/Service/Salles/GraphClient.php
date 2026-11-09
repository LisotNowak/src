<?php

namespace App\Service\Salles;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Accès à Microsoft Graph avec l'identité de l'application Azure AD du site
 * (même inscription que la connexion Microsoft : OAUTH_MICROSOFT_*).
 *
 * Autorisations d'APPLICATION nécessaires (consentement administrateur) :
 *  - Place.Read.All      : liste des salles de réunion
 *  - Calendars.ReadWrite : lecture des calendriers des salles, création/annulation
 *                          des réunions dans le calendrier de l'utilisateur
 */
class GraphClient
{
    private const BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $tenant,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    /**
     * Lance une requête sans attendre la réponse (Symfony HttpClient est asynchrone :
     * plusieurs requêtes lancées à la suite partent en parallèle).
     */
    public function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $options['headers'] = ($options['headers'] ?? []) + [
            'Authorization' => 'Bearer ' . $this->token(),
            // Toutes les dates échangées avec Graph sont en UTC, la conversion se fait côté PHP
            'Prefer' => 'outlook.timezone="UTC"',
        ];

        $url = str_starts_with($path, 'https://') ? $path : self::BASE . $path;

        return $this->httpClient->request($method, $url, $options);
    }

    /** Décode la réponse ou lève une GraphException avec le message renvoyé par Graph. */
    public function decode(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $data = $status === 204 ? [] : $response->toArray(false);

        if ($status >= 400) {
            throw new GraphException($data['error']['message'] ?? ('Erreur Microsoft Graph (HTTP ' . $status . ')'), $status);
        }

        return $data;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->decode($this->request('GET', $path, ['query' => $query]));
    }

    public function post(string $path, array $json): array
    {
        return $this->decode($this->request('POST', $path, ['json' => $json]));
    }

    /** Suit la pagination (@odata.nextLink) et renvoie toutes les valeurs. */
    public function getAll(string $path, array $query = []): array
    {
        $values = [];
        $data = $this->get($path, $query);
        while (true) {
            array_push($values, ...($data['value'] ?? []));
            if (empty($data['@odata.nextLink'])) {
                return $values;
            }
            $data = $this->get($data['@odata.nextLink']);
        }
    }

    private function token(): string
    {
        if ($this->clientSecret === '') {
            throw new GraphException('OAUTH_MICROSOFT_CLIENT_SECRET n\'est pas configuré.');
        }

        return $this->cache->get('salles_graph_token_' . md5($this->tenant . $this->clientId), function (ItemInterface $item): string {
            $response = $this->httpClient->request('POST', 'https://login.microsoftonline.com/' . rawurlencode($this->tenant) . '/oauth2/v2.0/token', [
                'body' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ]);
            $data = $response->toArray(false);

            if (empty($data['access_token'])) {
                throw new GraphException('Jeton Microsoft Graph refusé : ' . ($data['error_description'] ?? $data['error'] ?? 'réponse inattendue'));
            }

            // Marge de 5 minutes avant l'expiration réelle
            $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 3600) - 300));

            return $data['access_token'];
        });
    }
}
