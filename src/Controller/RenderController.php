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

    #[Route('/api/save-time-entries', name: 'api_save_time_entries', methods: ['POST'])]
    public function saveTimeEntries(Request $request, SqlServerService $sqlServerService): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['groupId'], $data['week'], $data['heures'])) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        $groupId = $data['groupId'];
        $week = $data['week'];
        $heures = $data['heures'];

        // Récupère les utilisateurs du groupe
        $users = $sqlServerService->query(
            "SELECT Employee_Id FROM TimeEntryGroupEmployees WHERE TimeEntryGroup_Id = :groupId",
            ['groupId' => $groupId]
        );

        if (empty($users)) {
            return $this->json(['error' => 'Aucun utilisateur dans ce groupe'], 400);
        }

        // Pour chaque utilisateur du groupe, insère les heures pour chaque jour
        foreach ($users as $user) {
            $employeeId = $user['Employee_Id'];
            foreach ($heures as $jour) {
                // Vérifie qu'il y a au moins une heure à enregistrer
                $hasHours = false;
                foreach ($jour as $key => $value) {
                    if (in_array($key, [
                        'HSaisie','HNorm','HRepComp','HCompl','HS10','HRepComp10','HS25','HRepComp25','HS50','HRepComp50','HS100','HRepComp100','RTT'
                    ]) && $value !== null && $value !== '') {
                        $hasHours = true;
                        break;
                    }
                }
                if (!$hasHours) continue;

                // --- DEBUT TRANSACTION ---
                $sqlServerService->beginTransaction();
                try {
                    // 1. INSERT TimeEntry
                    $sqlServerService->execute(
                        "INSERT INTO TimeEntries (
                            Employee_Id, DateEntry, NbHoursNormal, NbHoursRecoveryTime, NbHoursAdd, NbHoursAdd10, NbHoursRecoveryTime10,
                            NbHoursAdd25, NbHoursRecoveryTime25, NbHoursAdd50, NbHoursRecoveryTime50, NbHoursAdd100, NbHoursRecoveryTime100, NbHoursRtt
                        ) VALUES (
                            :employeeId, :dateEntry, :NbHoursNormal, :NbHoursRecoveryTime, :NbHoursAdd, :NbHoursAdd10, :NbHoursRecoveryTime10,
                            :NbHoursAdd25, :NbHoursRecoveryTime25, :NbHoursAdd50, :NbHoursRecoveryTime50, :NbHoursAdd100, :NbHoursRecoveryTime100, :NbHoursRtt
                        )",
                        [
                            'employeeId' => $employeeId,
                            'dateEntry' => isset($jour['date']) && $jour['date'] ? (new \DateTime($jour['date']))->format('Ymd') : null,
                            'NbHoursNormal' => $jour['HNorm'] ?? null,
                            'NbHoursRecoveryTime' => $jour['HRepComp'] ?? null,
                            'NbHoursAdd' => $jour['HCompl'] ?? null,
                            'NbHoursAdd10' => $jour['HS10'] ?? null,
                            'NbHoursRecoveryTime10' => $jour['HRepComp10'] ?? null,
                            'NbHoursAdd25' => $jour['HS25'] ?? null,
                            'NbHoursRecoveryTime25' => $jour['HRepComp25'] ?? null,
                            'NbHoursAdd50' => $jour['HS50'] ?? null,
                            'NbHoursRecoveryTime50' => $jour['HRepComp50'] ?? null,
                            'NbHoursAdd100' => $jour['HS100'] ?? null,
                            'NbHoursRecoveryTime100' => $jour['HRepComp100'] ?? null,
                            'NbHoursRtt' => $jour['RTT'] ?? null,
                        ]
                    );
                    // 2. Récupère l'ID du dernier TimeEntry inséré
                    $timeEntryIdResult = $sqlServerService->query("SELECT SCOPE_IDENTITY() AS TimeEntryId");
                    error_log('timeEntryIdResult: ' . print_r($timeEntryIdResult, true));
                    $timeEntryId = $sqlServerService->lastInsertId();
                    error_log('timeEntryId: ' . $timeEntryId);
                    if ($timeEntryId) {
                        $nbHours = $jour['HSaisie'] ?? 0;
                        $sqlServerService->execute(
                            "INSERT INTO TimeEntryVentilations (
                                NbHours, Comments, TimeEntry_Id, Task_Id, Parcelle_Id, Millesim_Id, IsBonus, WineAppellation_Id
                            ) VALUES (
                                :nbHours, :comments, :timeEntryId, 5, NULL, 1, 0, NULL
                            )",
                            [
                                'nbHours' => $nbHours,
                                'comments' => null,
                                'timeEntryId' => $timeEntryId,
                            ]
                        );
                    }
                    $sqlServerService->commit();
                } catch (\Throwable $e) {
                    $sqlServerService->rollBack();
                    throw $e;
                }
                // --- FIN TRANSACTION ---
            }
        }

        return $this->json(['success' => true]);
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