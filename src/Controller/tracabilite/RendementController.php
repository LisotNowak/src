<?php

namespace App\Controller\tracabilite;

use App\Repository\tracabilite\SaisieRepository;
use App\Service\tracabilite\EquipeAccess;
use App\Service\tracabilite\FiltreTaches;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER_TRACABILITE')]
class RendementController extends AbstractController
{
    public function __construct(
        private readonly SaisieRepository $saisieRepo,
        private readonly EquipeAccess     $equipeAccess,
        private readonly FiltreTaches     $filtreTaches,
    ) {}

    #[Route('/tracabilite/rendement', name: 'app_tracabilite_rendement')]
    public function index(Request $request): Response
    {
        $mois = $request->query->get('mois', date('Y-m'));
        $chef  = $request->query->get('chef', '');

        $filtre = $this->filtreTaches->resoudre($request, $this->saisieRepo->findTachesTerrainSurPeriode($mois, true));
        $tache = $filtre['tache'];
        $typeTache = $filtre['typeTache'];

        return $this->render('tracabilite/rendement/index.html.twig', [
            'active_link' => 'rendement',
            'mois'        => $mois,
            'chef'        => $chef,
            'typeTache'   => $typeTache,
            'typesTache'  => $filtre['typesTache'],
            'tache'       => $tache,
            'rendements'  => $this->saisieRepo->getRendementParPersonne($mois, $chef, $tache, $typeTache),
            'equipes'     => $this->equipeAccess->equipesVisibles(),
            'taches'      => $filtre['taches'],
        ]);
    }
}
