<?php
// src/Service/EventService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class EventService
{
    private HttpClientInterface $client;
    private string $token;

    public function __construct(HttpClientInterface $client, string $token)
    {
        $this->client = $client;
        $this->token = $token;
    }

    /**
     * Récupère les événements pour une période donnée
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return array
     */
    public function fetchEvents(\DateTime $startDate = null, \DateTime $endDate = null): array
    {
        try {
            // Si aucune date de début ou de fin n'est fournie, utiliser des valeurs par défaut
            $startDate = $startDate ?? new \DateTime('first day of this month');
            $endDate = $endDate ?? new \DateTime('last day of this month');
            
            // Formatage des dates
            $startDateFormatted = $startDate->format('Y-m-d');
            $endDateFormatted = $endDate->format('Y-m-d');

            // Préparer les données de la requête
            $postData = [
                'du' => $startDateFormatted,
                'au' => $endDateFormatted,
            ];

            // Effectuer la requête POST pour récupérer les événements
            $response = $this->client->request(
                'POST',
                'https://artemis-domaines.oenomanager.com/api/public/evenements',
                [
                    'headers' => [
                        'x-oenomanager-token' => $this->token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $postData,
                    'verify_peer' => false, // Désactiver la vérification SSL
                ]
            );

            // Traiter la réponse JSON
            return $response->toArray(); // Renvoie directement le contenu de la réponse sous forme de tableau
        } catch (TransportExceptionInterface $e) {
            // Gérer les exceptions
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }
}
