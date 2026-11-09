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
class AvancementController extends AbstractController
{
    public function __construct(
        private readonly SaisieRepository $saisieRepo,
        private readonly EquipeAccess     $equipeAccess,
        private readonly FiltreTaches     $filtreTaches,
    ) {}

    #[Route('/tracabilite/avancement', name: 'app_tracabilite_avancement')]
    public function index(Request $request): Response
    {
        // Sans paramètre : mois courant ; paramètre vide : tous les mois
        $mois = $request->query->has('mois') ? (string) $request->query->get('mois') : date('Y-m');
        if ($mois !== '' && !preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = date('Y-m');
        }
        $chef  = $request->query->get('chef', '');

        $filtre = $this->filtreTaches->resoudre($request, $this->saisieRepo->findTachesTerrainSurPeriode($mois));
        $tache = $filtre['tache'];
        $typeTache = $filtre['typeTache'];

        $avancements = $this->saisieRepo->getAvancementParParcelle($mois, $chef, $tache, $typeTache);

        return $this->render('tracabilite/avancement/index.html.twig', [
            'active_link'  => 'avancement',
            'mois'         => $mois,
            'chef'         => $chef,
            'typeTache'    => $typeTache,
            'typesTache'   => $filtre['typesTache'],
            'tache'        => $tache,
            'avancements'  => $avancements,
            // Nombre de tâches menées en parallèle sur chaque parcelle
            'nbTachesParcelle' => array_count_values(array_column($avancements, 'parcelleNom')),
            'equipes'      => $this->equipeAccess->equipesVisibles(),
            'taches'       => $filtre['taches'],
            'nonTerminees' => $this->saisieRepo->findParcellesNonTerminees('', $tache, $typeTache),
        ]);
    }
}
