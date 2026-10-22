<?php

namespace App\Controller\tracabilite;

use App\Repository\tracabilite\SaisieRepository;
use App\Service\tracabilite\EquipeAccess;
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
    ) {}

    #[Route('/tracabilite/rendement', name: 'app_tracabilite_rendement')]
    public function index(Request $request): Response
    {
        $mois = $request->query->get('mois', date('Y-m'));
        $chef  = $request->query->get('chef', '');
        $tache = (string) $request->query->get('tache', '');

        $taches = $this->saisieRepo->findTachesTerrainSurPeriode($mois, true);
        if (!in_array($tache, $taches, true)) {
            $tache = '';
        }

        return $this->render('tracabilite/rendement/index.html.twig', [
            'active_link' => 'rendement',
            'mois'        => $mois,
            'chef'        => $chef,
            'tache'       => $tache,
            'rendements'  => $this->saisieRepo->getRendementParPersonne($mois, $chef, $tache),
            'equipes'     => $this->equipeAccess->equipesVisibles(),
            'taches'      => $taches,
        ]);
    }
}
