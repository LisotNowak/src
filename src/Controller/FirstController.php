<?php
// src/Controller/FirstController.php
namespace App\Controller;

use App\Service\EventService;  // Mise à jour du namespace pour pointer vers le bon fichier
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use GuzzleHttp\Client;
use DateTime;

class FirstController extends AbstractController
{
    private $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    #[Route('/getAllEvents', name: 'app_getAllEvents')]
    public function getAllEvents(Request $request): Response
    {
        $mois = $request->query->get('mois', date('m')); // Par défaut, mois actuel
        $annee = $request->query->get('annee', date('Y')); // Par défaut, année actuelle

        // Définir la période du mois sélectionné
        $dateDebut = new \DateTime("$annee-$mois-01");
        $dateFin = clone $dateDebut;
        $dateFin->modify('last day of this month');

        // Récupérer tous les événements
        $allEvents = $this->eventService->fetchEvents($dateDebut, $dateFin);

        //var_dump($allEvents);

        return $this->render('calendrier.html.twig', [
            'allEvents' => $allEvents,
            'mois' => $mois,
            'annee' => $annee,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
        ]);
    }

    #[Route('/calendrierAllEvents', name: 'app_calendrierAllEvents')]
    public function calendrierAllEvents(): Response
    {
        // Logique pour récupérer les événements de l'API
        // ...
        return $this->render('calendrier.html.twig', [
            'allEvents' => $allEvents
        ]);
    }

    #[Route('/getEvents', name: 'app_getEvents')]
    public function getEvents(Request $request): Response
    {
        $semaine = $request->query->get('semaine', 1); // Par défaut, afficher la semaine 1
        $dateDebut = new \DateTime();
        $dateDebut->modify('monday this week');
        $dateDebut->modify(($semaine - 1) * 7 . ' days');


        $dateFin = clone $dateDebut;
        $dateFin->modify('last day of this month');

        // Récupérer tous les événements
        $allEvents = $this->eventService->fetchEvents($dateDebut, $dateFin);

        $formationEvents = array_filter($allEvents, function($event) {
            return isset($event['categorie']['nom']) && $event['categorie']['nom'] === 'Formation';
        });


        return $this->render('formation.html.twig', [
            'allEvents' => $formationEvents,
            'semaine' => $semaine,
            'dateDebut' => $dateDebut,
        ]);
    }

    #[Route('/calendrier', name: 'app_calendrier')]
public function calendrier(): Response
{
    try {
        $TOKEN = 'D#FGHD3$57FG=H2D4F(GH#DFGS6£QS5D@68F7$¤¤';

        // Définir les dates de début et de fin
        $dateMax = new DateTime(); // Obtenir la date actuelle
        $dateMax->modify('+1 year'); // Ajouter 1 an

        $postData = [
            'du' => date("Y-m-d"),
            'au' => $dateMax->format('Y-m-d')
        ];

        // Créer le client HTTP pour la requête
        $client = new Client([
            'verify' => false, // Désactiver la vérification SSL
        ]);
        $response = $client->post(
            'https://artemis-domaines.oenomanager.com/api/public/evenements', 
            [
                'headers' => [
                    'x-oenomanager-token' => $TOKEN,
                    'Content-Type' => 'application/json',
                ],
                'json' => $postData,
            ]
        );

        // Traiter la réponse JSON
        $responseBody = $response->getBody()->getContents();
        $allEvents = json_decode($responseBody, true);

        // Transformation des dates dans le format désiré
        foreach ($allEvents as &$event) {
            if (isset($event['date'])) {
                $eventDate = new \DateTime($event['date']);
                // Formater la date au format 'd/m/Y' (jour/mois/année)
                $event['date'] = $eventDate->format('d/m/Y');
            }
        }

    } catch (Exception $ex) {
        error_log($ex->getMessage());
        http_response_code(400);
        echo json_encode(['error' => $ex->getMessage()]);
    }

    return $this->render('formation.html.twig', [
        'allEvents' => $allEvents
    ]);
}

}
