<?php

namespace App\Controller\tracabilite;

use App\Repository\tracabilite\ParcelleRepository;
use App\Repository\tracabilite\SaisieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CarteController extends AbstractController
{
    public function __construct(
        private readonly ParcelleRepository $parcelleRepo,
        private readonly SaisieRepository   $saisieRepo,
    ) {}

    #[Route('/tracabilite/carte', name: 'app_tracabilite_carte')]
    public function index(): Response
    {
        return $this->render('tracabilite/carte/index.html.twig', [
            'active_link' => 'carte',
        ]);
    }

    #[Route('/tracabilite/carte/api/geojson', name: 'app_tracabilite_carte_geojson')]
    public function geojson(): JsonResponse
    {
        $parcelles   = $this->parcelleRepo->findAvecGeometrie();
        $avancements = $this->saisieRepo->getAvancementGlobal();

        $advMap = [];
        foreach ($avancements as $a) {
            $slug = $a['parcelleNom'] ?? '';
            $advMap[$slug] = max($advMap[$slug] ?? 0, (float)($a['maxAdv'] ?? 0));
        }

        $features = [];
        foreach ($parcelles as $p) {
            if (!$p->getGeometrie()) {
                continue;
            }
            $slug = $p->getSlug();
            $features[] = [
                'type'       => 'Feature',
                'properties' => [
                    'slug'        => $slug,
                    'cepage'      => $p->getCepage(),
                    'gamme'       => $p->getGamme(),
                    'nombrePieds' => $p->getNombrePieds(),
                    'surface'     => $p->getSurface(),
                    'avancement'  => $advMap[$slug] ?? 0,
                ],
                'geometry' => $p->getGeometrie(),
            ];
        }

        return $this->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
