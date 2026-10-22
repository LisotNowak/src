<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\log\ConnexionLogRepository;
use App\Repository\log\PageVisiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LogsAdminController extends AbstractController
{
    /** Durée de conservation proposée pour la purge */
    private const CONSERVATION_JOURS = 90;

    public function __construct(
        private ConnexionLogRepository $connexionLogs,
        private PageVisiteRepository $pageVisites,
    ) {
    }

    #[Route('/admin/logs', name: 'admin_logs_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $du = $this->date($request->query->get('du'), new \DateTimeImmutable('-7 days'))->setTime(0, 0);
        $au = $this->date($request->query->get('au'), new \DateTimeImmutable())->setTime(23, 59, 59);
        $email = trim((string) $request->query->get('email')) ?: null;

        return $this->render('admin/logs/index.html.twig', [
            'du' => $du,
            'au' => $au,
            'email' => $email,
            'statsConnexion' => $this->connexionLogs->stats($du, $au, $email),
            'statsPages' => $this->pageVisites->stats($du, $au, $email),
            'connexions' => $this->connexionLogs->findJournal($du, $au, $email),
            'echecsRepetes' => $this->connexionLogs->findEchecsRepetes($du, $au),
            'utilisateurs' => $this->pageVisites->parUtilisateur($du, $au, $email),
            'pages' => $this->pageVisites->parPage($du, $au, $email),
            'journal' => $this->pageVisites->findJournal($du, $au, $email),
            'conservationJours' => self::CONSERVATION_JOURS,
        ]);
    }

    #[Route('/admin/logs/purge', name: 'admin_logs_purge', methods: ['POST'])]
    public function purge(Request $request): Response
    {
        if ($this->isCsrfTokenValid('purge_logs', $request->request->get('_token'))) {
            $avant = new \DateTimeImmutable('-'.self::CONSERVATION_JOURS.' days');
            $nb = $this->connexionLogs->purger($avant) + $this->pageVisites->purger($avant);
            $this->addFlash('success', $nb.' entrée(s) de log supprimée(s)');
        }

        return $this->redirectToRoute('admin_logs_index');
    }

    /** Reçoit le temps actif passé sur une page (navigator.sendBeacon depuis base.html.twig) */
    #[Route('/log/temps', name: 'app_log_temps', methods: ['POST'])]
    public function temps(Request $request): Response
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if ($user instanceof User && is_array($data) && is_string($data['id'] ?? null) && is_numeric($data['s'] ?? null)) {
            $this->pageVisites->majTempsActif(mb_substr($data['id'], 0, 36), $user->getId(), (int) $data['s']);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function date(?string $valeur, \DateTimeImmutable $defaut): \DateTimeImmutable
    {
        return ($valeur ? \DateTimeImmutable::createFromFormat('Y-m-d', $valeur) : false) ?: $defaut;
    }
}
