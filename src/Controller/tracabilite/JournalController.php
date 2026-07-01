<?php

namespace App\Controller\tracabilite;

use App\Repository\tracabilite\EquipeRepository;
use App\Repository\tracabilite\JournalModificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JournalController extends AbstractController
{
    public function __construct(
        private readonly JournalModificationRepository $journalRepo,
        private readonly EquipeRepository              $equipeRepo,
    ) {}

    #[Route('/tracabilite/journal', name: 'app_tracabilite_journal')]
    public function index(Request $request): Response
    {
        $chef  = $request->query->get('chef', '');
        $mois  = $request->query->get('mois', date('Y-m'));
        $limit = (int) $request->query->get('limit', 200);

        if ($chef !== '') {
            $entrees = $this->journalRepo->findByChefEtMois($chef, $mois);
        } else {
            $entrees = $this->journalRepo->findRecentes($limit);
        }

        return $this->render('tracabilite/journal/index.html.twig', [
            'active_link' => 'journal',
            'entrees'     => $entrees,
            'equipes'     => $this->equipeRepo->findAllSorted(),
            'stats'       => $this->journalRepo->countByAction(),
            'chef'        => $chef,
            'mois'        => $mois,
        ]);
    }
}
