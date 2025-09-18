<?php
// src/Controller/RenderController.php
namespace App\Controller;

use DateTimeImmutable;
use App\Service\SqlServerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/calculette/user', name: 'app_calculette_user')]
    public function calculette(SqlServerService $sqlServerService): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $groups = $sqlServerService->query("SELECT * FROM TimeEntryGroups");
            $tasks = $sqlServerService->query("SELECT Id, Label FROM Tasks WHERE PossibleTimeEntry = 1");

            // Récupérer les utilisateurs
            $users = $sqlServerService->query(
                "SELECT Id, FirstName, LastName
                FROM AspNetUsers"
            );

            return $this->render('calculette/user.html.twig', [
                'groups' => $groups,
                'tasks' => $tasks,
                'users' => $users,
            ]);
        }
        return $this->redirectToRoute('app_accueil');
    }

    #[Route('/calculette', name: 'app_calculette_equipe')]
    public function calculette_equipe(SqlServerService $sqlServerService): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Récupérer les groupes
            $groups = $sqlServerService->query("SELECT * FROM TimeEntryGroups");

            // Récupérer les tâches
            $tasks = $sqlServerService->query("SELECT Id, Label FROM Tasks WHERE PossibleTimeEntry = 1");

            return $this->render('calculette/equipe.html.twig', [
                'groups' => $groups,
                'tasks' => $tasks,
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
            "SELECT Id, FirstName, LastName FROM SeasonalWorkers WHERE Id = :id",
            ['id' => (int)$userId]
        );
        $user = $userResults[0] ?? null;

        if (!$user) {
            throw $this->createNotFoundException("Travailleur saisonnier non trouvé.");
        }

        [$startDate, $endDate] = $this->getStartAndEndDateFromIsoWeek($week);

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
            'userId' => (int)$userId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $users = $sqlServerService->query("SELECT Id, FirstName, LastName FROM SeasonalWorkers");

        return $this->render('calculette/equipe.html.twig', [
            'users' => $users,
            'user' => $user,
            'week' => $week,
            'timeEntries' => $timeEntries,
            'weekDates' => $weekDates,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedGroupId' => $request->query->get('group'),
            'selectedUserId' => $userId,
        ]);
    }

    #[Route('/nonpermanent/api/users-by-group', name: 'nonpermanent_api_users_by_group', methods: ['GET'])]
    public function usersByGroupNonPermanent(Request $request, SqlServerService $sqlServerService): Response
    {
        $groupId = (int)$request->query->get('groupId');
        if (!$groupId) {
            return $this->json([]);
        }

        $users = $sqlServerService->query(
            "SELECT sw.Id AS Employee_Id, sw.FirstName, sw.LastName
            FROM TimeEntryGroupSeasonalWorkers tge
            INNER JOIN SeasonalWorkers sw ON sw.Id = tge.SeasonalWorker_Id
            WHERE tge.TimeEntryGroup_Id = :groupId",
            ['groupId' => $groupId]
        );

        return $this->json($users);
    }

    #[Route('/nonpermanent/api/group-users-with-hours', name: 'nonpermanent_api_group_users_with_hours', methods: ['GET'])]
    public function groupUsersWithHoursNonPermanent(Request $request, SqlServerService $sqlServerService): Response
    {
        $groupId = (int)$request->query->get('groupId');
        $week = $request->query->get('week');
        if (!$groupId || !$week) {
            return $this->json([]);
        }

        [$startDate, $endDate] = $this->getStartAndEndDateFromIsoWeek($week);

        $users = $sqlServerService->query(
            "SELECT DISTINCT sw.Id AS SeasonalWorker_Id, sw.FirstName, sw.LastName
            FROM TimeEntryGroupSeasonalWorkers tge
            INNER JOIN SeasonalWorkers sw ON sw.Id = tge.SeasonalWorker_Id
            INNER JOIN TimeEntries te ON te.SeasonalWorker_Id = sw.Id
            WHERE tge.TimeEntryGroup_Id = :groupId
            AND te.DateEntry >= :startDate
            AND te.DateEntry <= :endDate",
            [
                'groupId' => $groupId,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]
        );

        return $this->json($users);
    }


    #[Route('/nonpermanent/api/save-time-entries', name: 'nonpermanent_api_save_time_entries', methods: ['POST'])]
    public function saveTimeEntriesNonPermanent(Request $request, SqlServerService $sqlServerService): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['groupId'], $data['week'], $data['heures'], $data['taskId'])) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        $taskId = $data['taskId'];
        $groupId = (int)$data['groupId'];
        $week = $data['week'];
        $heures = $data['heures'];

        $users = $sqlServerService->query(
            "SELECT sw.Id AS Employee_Id, sw.FirstName, sw.LastName
            FROM TimeEntryGroupSeasonalWorkers tge
            INNER JOIN SeasonalWorkers sw ON sw.Id = tge.SeasonalWorker_Id
            WHERE tge.TimeEntryGroup_Id = :groupId",
            ['groupId' => $groupId]
        );

        if (empty($users)) {
            return $this->json(['error' => 'Aucun utilisateur dans ce groupe'], 400);
        }

        foreach ($users as $user) {
            $employeeId = (int)$user['Employee_Id'];
            foreach ($heures as $jour) {
                $hasHours = false;
                foreach ($jour as $key => $value) {
                    if (in_array($key, [
                        'HSaisie','HNorm','HRepComp','HCompl','HS10','HRepComp10','HS25','HRepComp25',
                        'HS50','HRepComp50','HS100','HRepComp100','RTT'
                    ]) && $value !== null && $value !== '') {
                        $hasHours = true;
                        break;
                    }
                }
                if (!$hasHours) continue;

                $sqlServerService->beginTransaction();
                try {

                    // Debug NbHoursNormal avant insert
                    error_log('Jour: ' . ($jour['date'] ?? 'inconnu') . ' - NbHoursNormal = ' . ($jour['HNorm'] ?? 0));
                    
                    $sqlServerService->execute(
                        "INSERT INTO TimeEntries (
                            SeasonalWorker_Id, DateEntry, NbHoursNormal, NbHoursRecoveryTime, NbHoursAdd, NbHoursAdd10, NbHoursRecoveryTime10,
                            NbHoursAdd25, NbHoursRecoveryTime25, NbHoursAdd50, NbHoursRecoveryTime50, NbHoursAdd100, NbHoursRecoveryTime100, NbHoursRtt
                        ) VALUES (
                            :seasonalWorkerId, :dateEntry, :NbHoursNormal, :NbHoursRecoveryTime, :NbHoursAdd, :NbHoursAdd10, :NbHoursRecoveryTime10,
                            :NbHoursAdd25, :NbHoursRecoveryTime25, :NbHoursAdd50, :NbHoursRecoveryTime50, :NbHoursAdd100, :NbHoursRecoveryTime100, :NbHoursRtt
                        )",
                        [
                            'seasonalWorkerId' => $employeeId, // ici ton $user['Employee_Id'] correspond en fait à sw.Id
                            'dateEntry' => isset($jour['date']) && $jour['date'] ? (new \DateTime($jour['date']))->format('Ymd') : null,
                            'NbHoursNormal' => $jour['HNorm'] ?: 0,
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


                    $timeEntryId = $sqlServerService->lastInsertId();

                    if ($timeEntryId) {
                        $nbHours = 
                            ($jour['HNorm'] ?? 0) +
                            ($jour['HRepComp'] ?? 0) +
                            ($jour['HCompl'] ?? 0) +
                            ($jour['HS10'] ?? 0) +
                            ($jour['HRepComp10'] ?? 0) +
                            ($jour['HS25'] ?? 0) +
                            ($jour['HRepComp25'] ?? 0) +
                            ($jour['HS50'] ?? 0) +
                            ($jour['HRepComp50'] ?? 0) +
                            ($jour['HS100'] ?? 0) +
                            ($jour['HRepComp100'] ?? 0);

                        $sqlServerService->execute(
                            "INSERT INTO TimeEntryVentilations (
                                NbHours, Comments, TimeEntry_Id, Task_Id, Parcelle_Id, Millesim_Id, IsBonus, WineAppellation_Id
                            ) VALUES (
                                :nbHours, :comments, :timeEntryId, :taskId, NULL, 1, 0, NULL
                            )",
                            [
                                'nbHours' => $nbHours,
                                'comments' => null,
                                'timeEntryId' => $timeEntryId,
                                'taskId' => $taskId,
                            ]
                        );
                    }
                    $sqlServerService->commit();
                } catch (\Throwable $e) {
                    $sqlServerService->rollBack();
                    throw $e;
                }
            }
        }

        return $this->json(['success' => true]);
    }

    private function getStartAndEndDateFromIsoWeek(string $isoWeek): array
    {
        $isoWeek = str_replace('-W', '', $isoWeek);

        $date = new DateTimeImmutable();
        $date = $date->setISODate(substr($isoWeek, 0, 4), substr($isoWeek, 4, 2));

        if (!$date) {
            throw new \InvalidArgumentException("Format de semaine invalide : $isoWeek");
        }

        $start = $date->setTime(0, 0, 0);
        $end = $start->modify('sunday this week')->setTime(23, 59, 59);

        return [
            $start->format('Y-m-d\TH:i:s'),
            $end->format('Y-m-d\TH:i:s'),
        ];
    }

    #[Route('/permanent/api/users-by-group', name: 'permanent_api_users_by_group', methods: ['GET'])]
    public function usersByGroupPermanent(Request $request, SqlServerService $sqlServerService): Response
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

    #[Route('/permanent/api/group-users-with-hours', name: 'permanent_api_group_users_with_hours', methods: ['GET'])]
    public function groupUsersWithHoursPermanent(Request $request, SqlServerService $sqlServerService): Response
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

    #[Route('/permanent/api/save-time-entries', name: 'permanent_api_save_time_entries', methods: ['POST'])]
    public function saveTimeEntriesPermanent(Request $request, SqlServerService $sqlServerService): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['groupId'], $data['week'], $data['heures'], $data['taskId'])) {
            return $this->json(['error' => 'Paramètres manquants'], 400);
        }

        $taskId = $data['taskId']; // <-- Ajout

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

                    // Debug NbHoursNormal avant insert
                    error_log('Jour: ' . ($jour['date'] ?? 'inconnu') . ' - NbHoursNormal = ' . ($jour['HNorm'] ?? 0));

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
                            'NbHoursNormal' => $jour['HNorm'] ?: 0,
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
                        $nbHours = 
                            ($jour['HNorm'] ?? 0) +
                            ($jour['HRepComp'] ?? 0) +
                            ($jour['HCompl'] ?? 0) +
                            ($jour['HS10'] ?? 0) +
                            ($jour['HRepComp10'] ?? 0) +
                            ($jour['HS25'] ?? 0) +
                            ($jour['HRepComp25'] ?? 0) +
                            ($jour['HS50'] ?? 0) +
                            ($jour['HRepComp50'] ?? 0) +
                            ($jour['HS100'] ?? 0) +
                            ($jour['HRepComp100'] ?? 0);

                        $sqlServerService->execute(
                            "INSERT INTO TimeEntryVentilations (
                                NbHours, Comments, TimeEntry_Id, Task_Id, Parcelle_Id, Millesim_Id, IsBonus, WineAppellation_Id
                            ) VALUES (
                                :nbHours, :comments, :timeEntryId, :taskId, NULL, 1, 0, NULL
                            )",
                            [
                                'nbHours' => $nbHours,
                                'comments' => null,
                                'timeEntryId' => $timeEntryId,
                                'taskId' => $taskId, // <-- Utilise la même tâche pour toute la semaine
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

   #[Route('/nonpermanent/api/user-with-hours', name: 'nonpermanent_api_user_with_hours', methods: ['GET'])]
public function userWithHoursNonPermanent(Request $request, SqlServerService $sqlServerService): Response
{
    $userId = (int)$request->query->get('userId');
    $week = $request->query->get('week');

    if (!$userId || !$week) {
        return $this->json([]);
    }

    [$startDate, $endDate] = $this->getStartAndEndDateFromIsoWeek($week);

    $entries = $sqlServerService->query(
        "SELECT * FROM TimeEntries
         WHERE SeasonalWorker_Id = :userId
           AND DateEntry >= :startDate
           AND DateEntry <= :endDate",
        [
            'userId' => $userId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]
    );

    return $this->json($entries);
}

#[Route('/nonpermanent/api/user/save-time-entries', name: 'nonpermanent_user_api_save_time_entries', methods: ['POST'])]
public function saveTimeEntriesUserNonPermanent(Request $request, SqlServerService $sqlServerService): Response
{
    $data = json_decode($request->getContent(), true);

    if (!$data || !isset($data['userId'], $data['week'], $data['heures'], $data['taskId'])) {
        return $this->json(['error' => 'Paramètres manquants'], 400);
    }

    $userId = (int)$data['userId'];
    $taskId = $data['taskId'];
    $heures = $data['heures'];

    foreach ($heures as $jour) {
        $hasHours = false;
        foreach ($jour as $key => $value) {
            if (in_array($key, [
                'HSaisie','HNorm','HRepComp','HCompl','HS10','HRepComp10','HS25','HRepComp25',
                'HS50','HRepComp50','HS100','HRepComp100','RTT'
            ]) && $value !== null && $value !== '') {
                $hasHours = true;
                break;
            }
        }
        if (!$hasHours) continue;

        $sqlServerService->beginTransaction();
        try {
            $sqlServerService->execute(
                "INSERT INTO TimeEntries (
                    SeasonalWorker_Id, DateEntry, NbHoursNormal, NbHoursRecoveryTime, NbHoursAdd, NbHoursAdd10, NbHoursRecoveryTime10,
                    NbHoursAdd25, NbHoursRecoveryTime25, NbHoursAdd50, NbHoursRecoveryTime50, NbHoursAdd100, NbHoursRecoveryTime100, NbHoursRtt
                ) VALUES (
                    :userId, :dateEntry, :NbHoursNormal, :NbHoursRecoveryTime, :NbHoursAdd, :NbHoursAdd10, :NbHoursRecoveryTime10,
                    :NbHoursAdd25, :NbHoursRecoveryTime25, :NbHoursAdd50, :NbHoursRecoveryTime50, :NbHoursAdd100, :NbHoursRecoveryTime100, :NbHoursRtt
                )",
                [
                    'userId' => $userId,
                    'dateEntry' => isset($jour['date']) && $jour['date'] ? (new \DateTime($jour['date']))->format('Y-m-d H:i:s') : null,
                    'NbHoursNormal' => $jour['HNorm'] ?: 0,
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

            $timeEntryId = $sqlServerService->lastInsertId();

            if ($timeEntryId) {
                $nbHours = 
                    ($jour['HNorm'] ?? 0) +
                    ($jour['HRepComp'] ?? 0) +
                    ($jour['HCompl'] ?? 0) +
                    ($jour['HS10'] ?? 0) +
                    ($jour['HRepComp10'] ?? 0) +
                    ($jour['HS25'] ?? 0) +
                    ($jour['HRepComp25'] ?? 0) +
                    ($jour['HS50'] ?? 0) +
                    ($jour['HRepComp50'] ?? 0) +
                    ($jour['HS100'] ?? 0) +
                    ($jour['HRepComp100'] ?? 0);

                $sqlServerService->execute(
                    "INSERT INTO TimeEntryVentilations (
                        NbHours, Comments, TimeEntry_Id, Task_Id, Parcelle_Id, Millesim_Id, IsBonus, WineAppellation_Id
                    ) VALUES (
                        :nbHours, :comments, :timeEntryId, :taskId, NULL, 1, 0, NULL
                    )",
                    [
                        'nbHours' => $nbHours,
                        'comments' => null,
                        'timeEntryId' => $timeEntryId,
                        'taskId' => $taskId,
                    ]
                );
            }

            $sqlServerService->commit();
        } catch (\Throwable $e) {
            $sqlServerService->rollBack();
            throw $e;
        }
    }

    return $this->json(['success' => true]);
}

#[Route('/permanent/api/user-with-hours', name: 'permanent_api_user_with_hours', methods: ['GET'])]
public function userWithHoursPermanent(Request $request, SqlServerService $sqlServerService): Response
{
    $userId = $request->query->get('userId'); // Employee_Id est nvarchar
    $week = $request->query->get('week');

    if (!$userId || !$week) {
        return $this->json([]);
    }

    [$startDate, $endDate] = $this->getStartAndEndDateFromIsoWeek($week);

    $entries = $sqlServerService->query(
        "SELECT * FROM TimeEntries
         WHERE Employee_Id = :userId
           AND DateEntry >= :startDate
           AND DateEntry <= :endDate",
        [
            'userId' => $userId,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]
    );

    return $this->json($entries);
}

#[Route('/permanent/api/user/save-time-entries', name: 'permanent_user_api_save_time_entries', methods: ['POST'])]
public function saveTimeEntriesUserPermanent(Request $request, SqlServerService $sqlServerService): Response
{
    $data = json_decode($request->getContent(), true);

    if (!$data || !isset($data['userId'], $data['week'], $data['heures'], $data['taskId'])) {
        return $this->json(['error' => 'Paramètres manquants'], 400);
    }

    $userId = $data['userId']; 
    $taskId = $data['taskId'];
    $heures = $data['heures'];
    $week = $data['week'];

    foreach ($heures as $jourIndex => $jour) {
        // Vérifie qu'il y a au moins une heure à enregistrer
        $hasHours = false;
        foreach ($jour as $key => $value) {
            if (in_array($key, [
                'HSaisie','HNorm','HRepComp','HCompl','HS10','HRepComp10','HS25','HRepComp25',
                'HS50','HRepComp50','HS100','HRepComp100','RTT'
            ]) && $value !== null && $value !== '') {
                $hasHours = true;
                break;
            }
        }
        if (!$hasHours) continue;

        // --- DEBUT TRANSACTION ---
        $sqlServerService->beginTransaction();
        try {
            // Conversion sécurisée de la date pour SQL Server
            $dateEntry = isset($jour['date']) && $jour['date']
                ? (new \DateTime($jour['date']))->format('Ymd') // format sûr pour SQL Server
                : null;

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
                    'employeeId' => $userId,
                    'dateEntry' => $dateEntry,
                    'NbHoursNormal' => $jour['HNorm'] ?? 0,
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
            $timeEntryId = $sqlServerService->lastInsertId();

            if ($timeEntryId) {
                $nbHours = 
                    ($jour['HNorm'] ?? 0) +
                    ($jour['HRepComp'] ?? 0) +
                    ($jour['HCompl'] ?? 0) +
                    ($jour['HS10'] ?? 0) +
                    ($jour['HRepComp10'] ?? 0) +
                    ($jour['HS25'] ?? 0) +
                    ($jour['HRepComp25'] ?? 0) +
                    ($jour['HS50'] ?? 0) +
                    ($jour['HRepComp50'] ?? 0) +
                    ($jour['HS100'] ?? 0) +
                    ($jour['HRepComp100'] ?? 0);

                $sqlServerService->execute(
                    "INSERT INTO TimeEntryVentilations (
                        NbHours, Comments, TimeEntry_Id, Task_Id, Parcelle_Id, Millesim_Id, IsBonus, WineAppellation_Id
                    ) VALUES (
                        :nbHours, :comments, :timeEntryId, :taskId, NULL, 1, 0, NULL
                    )",
                    [
                        'nbHours' => $nbHours,
                        'comments' => null,
                        'timeEntryId' => $timeEntryId,
                        'taskId' => $taskId,
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

    return $this->json(['success' => true]);
}

#[Route('/api/permanent-users', name: 'api_permanent_users', methods: ['GET'])]
public function getPermanentUsers(SqlServerService $sqlServerService): Response
{
    $users = $sqlServerService->query(
        "SELECT Id, FirstName, LastName 
         FROM AspNetUsers"
    );

    return $this->json($users);
}

#[Route('/api/nonpermanent-users', name: 'api_nonpermanent_users', methods: ['GET'])]
public function getNonPermanentUsers(SqlServerService $sqlServerService): Response
{
    $users = $sqlServerService->query(
        "SELECT Id, FirstName, LastName 
         FROM SeasonalWorkers"
    );

    return $this->json($users);
}




}
