<?php

namespace App\Controller\Inventaire;

use App\Entity\Inventaire\StockArticle;
use App\Repository\Inventaire\StockArticleRepository;
use App\Service\Inventaire\ImportStockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire', name: 'app_inventaire_')]
class InventaireController extends AbstractController
{
    public function __construct(
        private readonly StockArticleRepository $stockRepo,
        private readonly ImportStockService $importService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $depot       = $request->query->get('depot');
        $emplacement = $request->query->get('emplacement');
        $uniteMesure = $request->query->get('unite');
        $terme       = $request->query->get('q');
        $page        = max(1, (int) $request->query->get('page', 1));
        $limit       = 50;

        $result = $this->stockRepo->findWithFilters($depot, $emplacement, $uniteMesure, $terme, $page, $limit);

        $totalPages = (int) ceil($result['total'] / $limit);

        return $this->render('inventaire/index.html.twig', [
            'articles'     => $result['items'],
            'total'        => $result['total'],
            'page'         => $page,
            'totalPages'   => $totalPages,
            'limit'        => $limit,
            'depots'       => $this->stockRepo->findAllDepots(),
            'emplacements' => $this->stockRepo->findAllEmplacements(),
            'unites'       => $this->stockRepo->findAllUnites(),
            'filtreDepot'  => $depot,
            'filtreEmpl'   => $emplacement,
            'filtreUnite'  => $uniteMesure,
            'filtreQ'      => $terme,
        ]);
    }

    // ── Comptage ──────────────────────────────────────────────────────────

    #[Route('/comptage', name: 'comptage')]
    public function comptage(Request $request): Response
    {
        $emplacement = $request->query->get('emplacement');
        $terme       = $request->query->get('q');
        $page        = max(1, (int) $request->query->get('page', 1));
        $limit       = 50;

        $result = $this->stockRepo->findWithFilters(null, $emplacement, null, $terme, $page, $limit);

        return $this->render('inventaire/comptage.html.twig', [
            'articles'     => $result['items'],
            'total'        => $result['total'],
            'page'         => $page,
            'totalPages'   => (int) ceil($result['total'] / $limit),
            'limit'        => $limit,
            'emplacements' => $this->stockRepo->findAllEmplacements(),
            'filtreEmpl'   => $emplacement,
            'filtreQ'      => $terme,
            'csrf_token'   => $this->container->get('security.csrf.token_manager')->getToken('comptage_save')->getValue(),
        ]);
    }

    #[Route('/comptage/save/{id}', name: 'comptage_save', methods: ['POST'])]
    public function comptageSave(
        Request $request,
        StockArticle $article,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): JsonResponse {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false], 400);
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('comptage_save', $csrfToken))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $data = json_decode($request->getContent(), true);

        $comptage    = isset($data['comptage']) && $data['comptage'] !== '' ? $data['comptage'] : null;
        $commentaire = isset($data['commentaire']) && $data['commentaire'] !== '' ? $data['commentaire'] : null;

        $article->setComptage($comptage);
        $article->setCommentaire($commentaire);

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    // ── Import ────────────────────────────────────────────────────────────

    #[Route('/import', name: 'import', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function importForm(): Response
    {
        return $this->render('inventaire/import.html.twig');
    }

    #[Route('/import', name: 'import_process', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function importProcess(Request $request): Response
    {
        $file = $request->files->get('fichier_excel');

        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_inventaire_import');
        }

        $allowed = ['xlsx', 'xls'];
        $ext     = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, $allowed, true)) {
            $this->addFlash('danger', 'Format non autorisé. Utilisez un fichier .xlsx ou .xls.');
            return $this->redirectToRoute('app_inventaire_import');
        }

        $truncate = $request->request->get('mode') === 'truncate';

        try {
            $stats = $this->importService->import($file, $truncate);
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur lors de l\'import : ' . $e->getMessage());
            return $this->redirectToRoute('app_inventaire_import');
        }

        return $this->render('inventaire/import.html.twig', [
            'stats' => $stats,
        ]);
    }
}
