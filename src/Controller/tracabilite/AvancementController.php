<?php

namespace App\Controller\tracabilite;

use App\Repository\tracabilite\EquipeRepository;
use App\Repository\tracabilite\SaisieRepository;
use App\Repository\tracabilite\TacheRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AvancementController extends AbstractController
{
    public function __construct(
        private readonly SaisieRepository $saisieRepo,
        private readonly EquipeRepository $equipeRepo,
        private readonly TacheRepository  $tacheRepo,
    ) {}

    #[Route('/tracabilite/avancement', name: 'app_tracabilite_avancement')]
    public function index(Request $request): Response
    {
        $mois = $request->query->get('mois', date('Y-m'));
        $chef = $request->query->get('chef', '');

        return $this->render('tracabilite/avancement/index.html.twig', [
            'active_link'  => 'avancement',
            'mois'         => $mois,
            'chef'         => $chef,
            'avancements'  => $this->saisieRepo->getAvancementParParcelle($mois, $chef),
            'equipes'      => $this->equipeRepo->findAllSorted(),
            'taches'       => $this->tacheRepo->findTachesTerrain(),
            'nonTerminees' => $this->saisieRepo->findParcellesNonTerminees($mois),
        ]);
    }
}
