<?php
// src/Controller/FirstController.php
namespace App\Controller;

use Symfony\Component\Security\Core\Security;
use App\Service\EventService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use DateTime;

class FirstController extends AbstractController
{

    // --------------------------------------------- partie gestion des events --------------------------------------------------------------

    private $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }


    #[Route('/getEvents', name: 'app_getEvents')]
    public function getEvents(Request $request): Response
    {
        $semaine = $request->query->get('semaine', 1); // Par défaut, afficher la semaine 1
        $dateDebut = new \DateTime();
        $dateDebut->modify('monday this week');
        $dateDebut->modify(($semaine - 1) * 7 . ' days');

        $allEvents = $this->eventService->fetchEvents();

        $formationEvents = array_filter($allEvents, function($event) {
            return isset($event['categorie']['nom']) && $event['categorie']['nom'] === 'Formation';
        });


        return $this->render('calendrier.html.twig', [
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
    
            // var_dump($allEvents);
            
    
        } catch (Exception $ex) {
            error_log($ex->getMessage());
            http_response_code(400);
            echo json_encode(['error' => $ex->getMessage()]);
        }

        return $this->render('calendrier.html.twig', [
            'allEvents' => $allEvents
        ]);
    }
}