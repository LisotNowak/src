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

    public function fetchEvents(): array
    {
        try {
            // Définir les dates de début et de fin
            $dateMax = new \DateTime();
            $dateMax->modify('+1 year');

            $postData = [
                'du' => date("Y-m-d"),
                'au' => $dateMax->format('Y-m-d')
            ];

            // Effectuer la requête POST
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
