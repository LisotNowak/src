<?php

namespace App\Controller\tracabilite;

use App\Repository\tracabilite\CartePersoRepository;
use App\Repository\tracabilite\ParcelleRepository;
use App\Repository\tracabilite\SaisieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER_TRACABILITE')]
class CarteController extends AbstractController
{
    public function __construct(
        private readonly ParcelleRepository $parcelleRepo,
        private readonly SaisieRepository   $saisieRepo,
        private readonly CartePersoRepository $cartePersoRepo,
    ) {}

    #[Route('/tracabilite/carte', name: 'app_tracabilite_carte')]
    public function index(Request $request): Response
    {
        // Sans paramètre : mois courant ; paramètre vide : tous les mois
        $mois = $request->query->has('mois') ? (string) $request->query->get('mois') : date('Y-m');
        if ($mois !== '' && !preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = date('Y-m');
        }

        $taches = $this->saisieRepo->findTachesTerrainSurPeriode($mois);
        $tache  = (string) $request->query->get('tache', '');
        if (!in_array($tache, $taches, true)) {
            $tache = '';
        }

        // Disposition d'une carte personnalisée (parcelles repositionnées) au lieu des positions réelles
        $cartePerso = $request->query->getInt('carte') ? $this->cartePersoRepo->find($request->query->getInt('carte')) : null;

        return $this->render('tracabilite/carte/index.html.twig', [
            'active_link' => 'carte',
            'mois'        => $mois,
            'tache'       => $tache,
            'taches'      => $taches,
            'cartesPerso' => $this->cartePersoRepo->findAllSorted(),
            'cartePerso'  => $cartePerso,
        ]);
    }

    #[Route('/tracabilite/carte/api/geojson', name: 'app_tracabilite_carte_geojson')]
    public function geojson(Request $request): JsonResponse
    {
        $mois = (string) $request->query->get('mois', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
            $mois = '';
        }

        $parcelles   = $this->parcelleRepo->findAvecGeometrie();
        $avancements = $this->saisieRepo->getAvancementGlobal($mois);

        $advTacheMap = []; // avancement par parcelle et par tâche (une parcelle peut avoir plusieurs tâches en parallèle)
        foreach ($avancements as $a) {
            $advTacheMap[$a['parcelleNom'] ?? ''][$a['tacheNom'] ?? ''] = (float)($a['maxAdv'] ?? 0);
        }

        $features = [];
        foreach ($parcelles as $p) {
            if (!$p->getGeometrie()) {
                continue;
            }
            $slug = $p->getSlug();
            $advs = $advTacheMap[$slug] ?? [];
            $features[] = [
                'type'       => 'Feature',
                'properties' => [
                    'slug'        => $slug,
                    'cepage'      => $p->getCepage(),
                    'gamme'       => $p->getGamme(),
                    'nombrePieds' => $p->getNombrePieds(),
                    'surface'     => $p->getSurface(),
                    // Toutes tâches confondues : la parcelle n'est terminée que si toutes ses tâches le sont
                    'avancement'  => $advs ? min($advs) : 0,
                    'avancements' => (object) $advs,
                ],
                'geometry' => $p->getGeometrie(),
            ];
        }

        return $this->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
