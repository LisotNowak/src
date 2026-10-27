<?php

namespace App\Controller\Aruba;

use App\Service\Aruba\Api;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion switch (Aruba CX) : interface (une page, vues côté navigateur) et API JSON.
 * La collecte tourne en arrière-plan (php bin/console aruba:collect), voir App\Service\Aruba\Collector.
 */
#[Route('/gestion-switch', name: 'app_gestion_switch_')]
#[IsGranted('ROLE_GESTION_SWITCH')]
class ArubaController extends AbstractController
{
    public function __construct(
        private readonly Api $api,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('aruba/index.html.twig');
    }

    // ── API JSON (appelée par la page) ─────────────────────────────────────

    #[Route('/api/snapshot', name: 'snapshot', methods: ['GET'])]
    public function snapshot(): JsonResponse
    {
        return $this->json($this->api->snapshot());
    }

    #[Route('/api/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json($this->api->status());
    }

    #[Route('/api/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('aruba_refresh', $csrfToken))) {
            return $this->json(['started' => false, 'message' => 'Token CSRF invalide'], 403);
        }

        return $this->json($this->api->refresh());
    }

    #[Route('/api/history', name: 'history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        return $this->json($this->api->history((string) $request->query->get('q', '')));
    }

    #[Route('/export/macs.csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(): Response
    {
        return new Response($this->api->macsCsv(), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=macs.csv',
        ]);
    }
}
