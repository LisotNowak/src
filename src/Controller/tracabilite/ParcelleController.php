<?php

namespace App\Controller\tracabilite;

use App\Entity\tracabilite\Parcelle;
use App\Repository\tracabilite\CartePersoRepository;
use App\Repository\tracabilite\ParcelleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Gestion du référentiel des parcelles (super admin) */
#[Route('/tracabilite/parcelles', name: 'app_tracabilite_parcelle_')]
#[IsGranted('ROLE_ADMIN')]
class ParcelleController extends AbstractController
{
    public function __construct(
        private readonly ParcelleRepository   $parcelleRepo,
        private readonly CartePersoRepository $cartePersoRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('tracabilite/parcelle/index.html.twig', [
            'active_link' => 'parcelle',
            'parcelles'   => $this->parcelleRepo->findAllSorted(),
            'usages'      => $this->nbSaisiesParParcelle(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->formulaire($request, new Parcelle());
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $parcelle = $this->parcelleRepo->find($id) ?? throw $this->createNotFoundException('Parcelle introuvable.');
        return $this->formulaire($request, $parcelle);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request): Response
    {
        $parcelle = $this->parcelleRepo->find($id);
        if (!$parcelle || !$this->isCsrfTokenValid('parcelle_delete_'.$id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Suppression impossible.');
            return $this->redirectToRoute('app_tracabilite_parcelle_index');
        }

        $nb = $this->nbSaisiesParParcelle()[$parcelle->getSlug()] ?? 0;
        if ($nb > 0) {
            $this->addFlash('danger', "La parcelle « {$parcelle->getSlug()} » est utilisée dans {$nb} saisie(s) : elle ne peut pas être supprimée.");
            return $this->redirectToRoute('app_tracabilite_parcelle_index');
        }

        $this->renommerDansCartesPerso($parcelle->getSlug(), null);
        $this->em->remove($parcelle);
        $this->em->flush();

        $this->addFlash('success', "Parcelle « {$parcelle->getSlug()} » supprimée.");
        return $this->redirectToRoute('app_tracabilite_parcelle_index');
    }

    // -------------------------------------------------------------------------

    private function formulaire(Request $request, Parcelle $parcelle): Response
    {
        $creation    = $parcelle->getId() === null;
        $nbSaisies   = $creation ? 0 : ($this->nbSaisiesParParcelle()[$parcelle->getSlug()] ?? 0);
        $slugBloque  = $nbSaisies > 0;
        $erreurs     = [];
        $valeurs     = [
            'slug'        => $parcelle->getSlug() ?? '',
            'cepage'      => $parcelle->getCepage() ?? '',
            'gamme'       => $parcelle->getGamme() ?? '',
            'surface'     => $parcelle->getSurface() ?? '',
            'nombrePieds' => $parcelle->getNombrePieds() ?? '',
            'geometrie'   => $parcelle->getGeometrie() ? json_encode($parcelle->getGeometrie()) : '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('parcelle_form', $request->request->get('_token'))) {
                $erreurs['_'] = 'Le formulaire a expiré, rechargez la page.';
            }
            $p = $request->request;
            $valeurs = [
                'slug'        => $slugBloque ? $parcelle->getSlug() : trim((string) $p->get('slug')),
                'cepage'      => trim((string) $p->get('cepage')),
                'gamme'       => trim((string) $p->get('gamme')),
                'surface'     => trim(str_replace(',', '.', (string) $p->get('surface'))),
                'nombrePieds' => trim(str_replace(' ', '', (string) $p->get('nombrePieds'))),
                'geometrie'   => trim((string) $p->get('geometrie')),
            ];

            // Fichier GeoJSON importé : prioritaire sur le texte collé
            $fichier = $request->files->get('fichier_geojson');
            if ($fichier && $fichier->isValid()) {
                $valeurs['geometrie'] = trim((string) file_get_contents($fichier->getPathname()));
            }

            // Identifiant
            if ($valeurs['slug'] === '') {
                $erreurs['slug'] = "L'identifiant est requis.";
            } elseif (!preg_match('/^[\p{L}0-9_\-]+$/u', $valeurs['slug'])) {
                $erreurs['slug'] = 'Utilisez uniquement des lettres, chiffres, « _ » et « - » (sans espace).';
            } elseif (mb_strlen($valeurs['slug']) > 255) {
                $erreurs['slug'] = 'Identifiant trop long (255 caractères maximum).';
            } else {
                $existant = $this->parcelleRepo->findBySlug($valeurs['slug']);
                if ($existant && $existant->getId() !== $parcelle->getId()) {
                    $erreurs['slug'] = 'Une parcelle porte déjà cet identifiant.';
                }
            }

            // Surface (ha, 2 décimales, colonne DECIMAL(6,2))
            if ($valeurs['surface'] !== '' && (!is_numeric($valeurs['surface']) || $valeurs['surface'] < 0 || $valeurs['surface'] >= 10000)) {
                $erreurs['surface'] = 'Surface invalide (en hectares, ex : 1.25).';
            }
            // Nombre de pieds
            if ($valeurs['nombrePieds'] !== '' && !ctype_digit($valeurs['nombrePieds'])) {
                $erreurs['nombrePieds'] = 'Nombre de pieds invalide (entier positif).';
            }
            if (mb_strlen($valeurs['cepage']) > 100) {
                $erreurs['cepage'] = 'Cépage trop long (100 caractères maximum).';
            }
            if (mb_strlen($valeurs['gamme']) > 50) {
                $erreurs['gamme'] = 'Gamme trop longue (50 caractères maximum).';
            }

            // Géométrie
            $geometrie = null;
            if ($valeurs['geometrie'] !== '') {
                try {
                    $geometrie = $this->lireGeometrie($valeurs['geometrie']);
                } catch (\InvalidArgumentException $e) {
                    $erreurs['geometrie'] = $e->getMessage();
                }
            }

            if (!$erreurs) {
                $ancienSlug = $parcelle->getSlug();
                $parcelle
                    ->setSlug($valeurs['slug'])
                    ->setCepage($valeurs['cepage'] !== '' ? $valeurs['cepage'] : null)
                    ->setGamme($valeurs['gamme'] !== '' ? $valeurs['gamme'] : null)
                    ->setSurface($valeurs['surface'] !== '' ? number_format((float) $valeurs['surface'], 2, '.', '') : null)
                    ->setNombrePieds($valeurs['nombrePieds'] !== '' ? (int) $valeurs['nombrePieds'] : null)
                    ->setGeometrie($geometrie);

                if ($creation) {
                    $this->em->persist($parcelle);
                } elseif ($ancienSlug !== $valeurs['slug']) {
                    // Parcelle sans saisie renommée : les cartes personnalisées suivent
                    $this->renommerDansCartesPerso($ancienSlug, $valeurs['slug']);
                }
                $this->em->flush();

                $this->addFlash('success', $creation
                    ? "Parcelle « {$parcelle->getSlug()} » créée."
                    : "Parcelle « {$parcelle->getSlug()} » enregistrée.");
                return $this->redirectToRoute('app_tracabilite_parcelle_index');
            }
        }

        return $this->render('tracabilite/parcelle/form.html.twig', [
            'active_link' => 'parcelle',
            'parcelle'    => $parcelle,
            'creation'    => $creation,
            'valeurs'     => $valeurs,
            'erreurs'     => $erreurs,
            'slugBloque'  => $slugBloque,
            'nbSaisies'   => $nbSaisies,
            'cepages'     => $this->valeursDistinctes('cepage'),
            'gammes'      => $this->valeursDistinctes('gamme'),
        ], new Response(null, $erreurs ? 422 : 200));
    }

    /**
     * Lit une géométrie GeoJSON (Geometry, Feature ou FeatureCollection d'un seul élément)
     * et la renvoie en MultiPolygon, coordonnées en Lambert 93 (mètres).
     */
    private function lireGeometrie(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Le GeoJSON n\'est pas valide (JSON mal formé).');
        }
        if (($data['type'] ?? null) === 'FeatureCollection') {
            if (count($data['features'] ?? []) !== 1) {
                throw new \InvalidArgumentException('La FeatureCollection doit contenir exactement une entité (une parcelle).');
            }
            $data = $data['features'][0];
        }
        if (($data['type'] ?? null) === 'Feature') {
            $data = $data['geometry'] ?? null;
        }
        if (!is_array($data) || !in_array($data['type'] ?? null, ['Polygon', 'MultiPolygon'], true) || !is_array($data['coordinates'] ?? null)) {
            throw new \InvalidArgumentException('La géométrie doit être un Polygon ou un MultiPolygon.');
        }

        $polygones = $data['type'] === 'Polygon' ? [$data['coordinates']] : $data['coordinates'];
        $propre = [];
        foreach ($polygones as $anneaux) {
            if (!is_array($anneaux) || !$anneaux) {
                throw new \InvalidArgumentException('Polygone vide ou mal formé.');
            }
            $poly = [];
            foreach ($anneaux as $anneau) {
                if (!is_array($anneau) || count($anneau) < 4) {
                    throw new \InvalidArgumentException('Chaque contour doit avoir au moins 4 points (le premier répété à la fin).');
                }
                $points = [];
                foreach ($anneau as $pt) {
                    if (!is_array($pt) || count($pt) < 2 || !is_numeric($pt[0]) || !is_numeric($pt[1])) {
                        throw new \InvalidArgumentException('Coordonnée invalide dans la géométrie.');
                    }
                    [$x, $y] = [(float) $pt[0], (float) $pt[1]];
                    // Lambert 93 en France métropolitaine : x ≈ 100 000–1 250 000 m, y ≈ 6 000 000–7 200 000 m
                    if ($x < 100000 || $x > 1300000 || $y < 6000000 || $y > 7200000) {
                        throw new \InvalidArgumentException(abs($x) <= 180 && abs($y) <= 90
                            ? 'Les coordonnées semblent être en degrés (GPS / WGS84) : elles doivent être en Lambert 93 (EPSG:2154), en mètres.'
                            : 'Coordonnées hors de la France métropolitaine en Lambert 93 (EPSG:2154).');
                    }
                    $points[] = [round($x, 2), round($y, 2)];
                }
                $poly[] = $points;
            }
            $propre[] = $poly;
        }

        return ['type' => 'MultiPolygon', 'coordinates' => $propre];
    }

    /** @return array<string, int> nombre de saisies par identifiant de parcelle (toutes équipes) */
    private function nbSaisiesParParcelle(): array
    {
        $rows = $this->em->getConnection()->fetchAllKeyValue(
            'SELECT parcelle_nom, COUNT(*) FROM vigne_saisie WHERE parcelle_nom IS NOT NULL GROUP BY parcelle_nom'
        );
        return array_map('intval', $rows);
    }

    /** @return string[] */
    private function valeursDistinctes(string $champ): array
    {
        return $this->parcelleRepo->createQueryBuilder('p')
            ->select('DISTINCT p.' . $champ)
            ->where('p.' . $champ . ' IS NOT NULL')
            ->orderBy('p.' . $champ, 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /** Renomme (ou retire si $nouveau est null) une parcelle dans la disposition des cartes personnalisées */
    private function renommerDansCartesPerso(string $ancien, ?string $nouveau): void
    {
        foreach ($this->cartePersoRepo->findAll() as $carte) {
            $disposition = $carte->getDisposition();
            if (!array_key_exists($ancien, $disposition)) {
                continue;
            }
            $decalage = $disposition[$ancien];
            unset($disposition[$ancien]);
            if ($nouveau !== null) {
                $disposition[$nouveau] = $decalage;
            }
            $carte->setDisposition($disposition);
        }
    }
}
