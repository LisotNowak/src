<?php
// src/Controller/RenderController.php
namespace App\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use App\Service\SqlServerService;
use App\Entity\Product;
use App\Entity\Droit;
use App\Entity\AssociationDroitUser;
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
use Symfony\Component\Security\Core\Security;

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
    
    // private Security $security;

    // public function __construct(Security $security)
    // {
    //     $this->security = $security;
    // }

    #[Route('/calculette', name: 'app_calculette')]
    public function calculette(SqlServerService $sqlServerService): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {

            // $users = $sqlServerService->query("SELECT * FROM AspNetUsers");

            $groups = $sqlServerService->query("SELECT * FROM TimeEntryGroups");
            

            return $this->render('calculette.html.twig', [
                // 'users' => $users,
                'groups' => $groups,
            ]);
        }
        return $this->redirectToRoute('app_accueil');

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
            'weekDates' => $weekDates,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedGroupId' => $request->query->get('group'), // ou le nom de ton paramètre
            'selectedUserId' => $userId,
        ]);
    }

    #[Route('/api/users-by-group', name: 'api_users_by_group', methods: ['GET'])]
    public function usersByGroup(Request $request, SqlServerService $sqlServerService): Response
    {
        $groupId = $request->query->get('groupId');
        if (!$groupId) {
            return $this->json([]);
        }

        $users = $sqlServerService->query(
            "SELECT u.Id, u.FirstName, u.LastName
             FROM AspNetUsers u
             INNER JOIN TimeEntryGroupEmployees tge ON tge.Employee_Id = u.id
             WHERE tge.TimeEntryGroup_Id = :groupId",
            ['groupId' => $groupId]
        );

        return $this->json($users);
    }

    #[Route('/api/group-users-with-hours', name: 'api_group_users_with_hours', methods: ['GET'])]
    public function groupUsersWithHours(Request $request, SqlServerService $sqlServerService): Response
    {
        $groupId = $request->query->get('groupId');
        $week = $request->query->get('week');
        if (!$groupId || !$week) {
            return $this->json([]);
        }

        // Récupère les dates de la semaine
        [$startDate, $endDate] = $this->getStartAndEndDateFromIsoWeek($week);

        // Récupère les utilisateurs du groupe ayant des heures saisies cette semaine
        $users = $sqlServerService->query(
            "SELECT u.Id, u.FirstName, u.LastName
             FROM AspNetUsers u
             INNER JOIN TimeEntryGroupEmployees tge ON tge.Employee_Id = u.id
             INNER JOIN TimeEntries te ON te.Employee_Id = u.Id
             WHERE tge.TimeEntryGroup_Id = :groupId
             AND te.DateEntry >= :startDate AND te.DateEntry <= :endDate",
            [
                'groupId' => $groupId,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]
        );

        return $this->json($users);
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