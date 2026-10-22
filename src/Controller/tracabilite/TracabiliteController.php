<?php

namespace App\Controller\tracabilite;

use App\Repository\tracabilite\SaisieRepository;
use App\Service\tracabilite\EquipeAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tracabilite', name: 'app_tracabilite_')]
#[IsGranted('ROLE_MANAGER_TRACABILITE')]
class TracabiliteController extends AbstractController
{
    public function __construct(
        private readonly SaisieRepository $saisieRepo,
        private readonly EquipeAccess     $equipeAccess,
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(Request $request): Response
    {
        $mois = $request->query->get('mois', date('Y-m'));

        return $this->render('tracabilite/dashboard.html.twig', [
            'active_link'   => 'dashboard',
            'mois'          => $mois,
            'kpis'          => $this->saisieRepo->getKpis($mois),
            'recentes'      => $this->saisieRepo->findRecentes(15),
            'equipes'       => $this->equipeAccess->equipesVisibles(),
            'nonTerminees'  => $this->saisieRepo->findParcellesNonTerminees(),
        ]);
    }
}
