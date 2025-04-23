<?php
// src/Controller/RenderController.php
namespace App\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use App\Service\SqlServerService;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
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

class RenderController extends AbstractController
{
    #[Route('/', name: 'app_accueil')]
    public function number(): Response
    {

        return $this->render('accueil.html.twig');
    }

    #[Route('/signature', name: 'app_signature')]
    public function signature(): Response
    {

        return $this->render('signature.html.twig');
    }

    #[Route('/organigramme', name: 'app_organigramme')]
    public function organigramme(): Response
    {

        return $this->render('organigramme/organigramme.html.twig');
    }
    

    #[Route('/calculette', name: 'app_calculette')]
    public function calculette(SqlServerService $sqlServerService): Response
    {
        $users = $sqlServerService->query("SELECT * FROM AspNetUsers");

        return $this->render('calculette.html.twig', [
            'users' => $users,

        ]);

    }

    #[Route('/calculette/resultat', name: 'app_calculette_resultat', methods: ['GET'])]
    public function calculetteResultat(Request $request, SqlServerService $sqlServerService): Response
    {
        $userId = $request->query->get('user');
        $week = $request->query->get('week');

        if (!$userId || !$week) {
            return $this->redirectToRoute('app_calculette');
        }

        $userResults = $sqlServerService->query(
            "SELECT * FROM AspNetUsers WHERE Id = :id", 
            ['id' => $userId]
        );
        $user = $userResults[0] ?? null;

        if (!$user) {
            throw $this->createNotFoundException("Utilisateur non trouvé.");
        }

        // Obtenir les dates de début et fin de semaine
        [$startDate, $endDate] = $this->getStartAndEndDateFromIsoWeek($week);

        // Calculer les dates de chaque jour de la semaine
        $weekDates = [];
        $currentDate = new DateTime($startDate);
        for ($i = 0; $i < 7; $i++) {
            $weekDates[] = $currentDate->format('Y-m-d');
            $currentDate->modify('+1 day');
        }

        $timeEntries = $sqlServerService->query("
            SELECT * FROM TimeEntries 
            WHERE Employee_Id = :userId 
            AND DateEntry >= :startDate 
            AND DateEntry <= :endDate
        ", [
            'userId' => $userId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $users = $sqlServerService->query("SELECT * FROM AspNetUsers");

        return $this->render('calculette.html.twig', [
            'users' => $users,
            'user' => $user,
            'week' => $week,
            'timeEntries' => $timeEntries,
            'weekDates' => $weekDates, // Passer les dates de la semaine à la vue
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    private function getStartAndEndDateFromIsoWeek(string $isoWeek): array
    {
        $isoWeek = str_replace('-W', '', $isoWeek); // ex: "2025-W17" → "202517"

        $date = new DateTimeImmutable();
        $date = $date->setISODate(substr($isoWeek, 0, 4), substr($isoWeek, 4, 2));

        if (!$date) {
            throw new \InvalidArgumentException("Format de semaine invalide : $isoWeek");
        }

        $start = $date->setTime(0, 0, 0); // Lundi
        $end = $start->modify('sunday this week')->setTime(23, 59, 59); // Dimanche

        return [
            $start->format('Y-m-d\TH:i:s'),
            $end->format('Y-m-d\TH:i:s'),
        ];
    }

}