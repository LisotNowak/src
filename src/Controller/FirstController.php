<?php
// src/Controller/FirstController.php
namespace App\Controller;

use App\Entity\calendrier\Event;
use Doctrine\ORM\EntityManagerInterface;
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
    private $entityManager;

    public function __construct(EventService $eventService, EntityManagerInterface $entityManager)
    {
        $this->eventService = $eventService;
        $this->entityManager = $entityManager;
    }

    #[Route('/getAllEvents', name: 'app_getAllEvents')]
    public function getAllEvents(Request $request): Response
    {
        $mois = $request->query->get('mois', date('m'));
        $annee = $request->query->get('annee', date('Y'));

        // Définir la période du mois sélectionné
        $dateDebut = new \DateTime("$annee-$mois-01");
        $dateFin = clone $dateDebut;
        // $dateFin->modify('last day of this month')->setTime(23, 59, 59);
        $dateFin->modify('first day of next month')->setTime(0, 0, 0);


        // Récupérer les événements depuis l'API
        $apiEvents = $this->eventService->fetchEvents($dateDebut, $dateFin);

        $apiEvents = array_filter($apiEvents, function ($event) {
            $validCategories = [
                'Visite',
                'Déjeuner Pavillon',
                'Dégustation',
                'Dîner Pavillon',
                'Dîner Château',
                'Déjeuner Extérieur',
                'Dîner Extérieur',
                'Formation',
                'Masterclass Extérieur'               
            ];
        
            if (!isset($event['categorie']['nom'])) {
                return false;
            }
        
            $nom = $event['categorie']['nom'];
        
            return in_array($nom, $validCategories) || strpos($nom, 'Château Latour') === 0;
        });
        
        
        // var_dump($apiEvents);


        // Récupérer les événements depuis la base de données
        $dbEvents = $this->entityManager->getRepository(Event::class)->createQueryBuilder('e')
            ->where('e.du BETWEEN :start AND :end OR e.au BETWEEN :start AND :end')
            ->setParameter('start', $dateDebut->format('Y-m-d'))
            ->setParameter('end', $dateFin->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        // Fusionner les événements
        $allEvents = array_merge($apiEvents, $dbEvents);

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
        // Récupérer tous les événements depuis la base de données
        $allEvents = $this->entityManager->getRepository(Event::class)->findAll();

        return $this->render('calendrier.html.twig', [
            'allEvents' => $allEvents
        ]);
    }

    #[Route('/addEvent', name: 'app_add_event', methods: ['POST'])]
    public function addEvent(Request $request, EntityManagerInterface $entityManager): Response
    {
        $label = $request->request->get('label');
        $du = new \DateTime($request->request->get('du'));
        $au = new \DateTime($request->request->get('au'));
        $description = $request->request->get('description');
        $auteur = $request->request->get('auteur');
        $type = $request->request->get('type');

        // Création de l'événement
        $event = new Event();
        $event->setLabel($label);
        $event->setDu($du->format('Y-m-d H:i:s'));
        $event->setAu($au->format('Y-m-d H:i:s'));
        $event->setDescription($description);
        $event->setAuteur($auteur);
        $event->setCategorie($type);


        // Sauvegarde en base de données
        $entityManager->persist($event);
        $entityManager->flush();

        return $this->redirectToRoute('app_getAllEvents');
    }

    #[Route('/event/{id}/delete', name: 'app_delete_event', methods: ['POST'])]
    public function delete(Request $request, Event $event, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->request->get('_token'))) {
            $em->remove($event);
            $em->flush();
            $this->addFlash('success', 'Événement supprimé avec succès');
        }

        return $this->redirectToRoute('app_getAllEvents');
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
