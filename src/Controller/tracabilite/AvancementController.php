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
class AvancementController extends AbstractController
{
    public function __construct(
        private readonly SaisieRepository $saisieRepo,
        private readonly EquipeAccess     $equipeAccess,
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
        $tache = (string) $request->query->get('tache', '');

        $taches = $this->saisieRepo->findTachesTerrainSurPeriode($mois);
        if (!in_array($tache, $taches, true)) {
            $tache = '';
        }

        $avancements = $this->saisieRepo->getAvancementParParcelle($mois, $chef, $tache);

        return $this->render('tracabilite/avancement/index.html.twig', [
            'active_link'  => 'avancement',
            'mois'         => $mois,
            'chef'         => $chef,
            'tache'        => $tache,
            'avancements'  => $avancements,
            // Nombre de tâches menées en parallèle sur chaque parcelle
            'nbTachesParcelle' => array_count_values(array_column($avancements, 'parcelleNom')),
            'equipes'      => $this->equipeAccess->equipesVisibles(),
            'taches'       => $taches,
            'nonTerminees' => $this->saisieRepo->findParcellesNonTerminees('', $tache),
        ]);
    }
}
