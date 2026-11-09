<?php

namespace App\Controller\Salles;

use App\Entity\User;
use App\Service\Salles\GraphException;
use App\Service\Salles\SalleService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réservation de salles Outlook : une page (planning côté navigateur) et une API JSON.
 * Les données viennent d'Exchange Online via Microsoft Graph, voir App\Service\Salles\SalleService.
 */
#[Route('/salles', name: 'app_salles_')]
#[IsGranted('ROLE_GESTION_SALLE')]
class SalleController extends AbstractController
{
    public function __construct(
        private readonly SalleService $salles,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('salles/index.html.twig', ['moi' => $this->moi()]);
    }

    // ── API JSON (appelée par la page) ─────────────────────────────────────

    #[Route('/api/planning', name: 'planning', methods: ['GET'])]
    public function planning(Request $request): JsonResponse
    {
        return $this->repondre(function () use ($request) {
            $jour = $this->salles->jour((string) $request->query->get('jour', ''));
            $jours = max(1, min(7, $request->query->getInt('jours', 1)));

            return [
                'jour' => $jour->format('Y-m-d'),
                'jours' => $jours,
                'salles' => $this->salles->planning($jour, $jour->modify('+' . $jours . ' days'), $this->moi()),
            ];
        });
    }

    #[Route('/api/reserver', name: 'reserver', methods: ['POST'])]
    public function reserver(Request $request): JsonResponse
    {
        if (!$this->csrfValide($request)) {
            return $this->json(['message' => 'Token CSRF invalide, rechargez la page.'], 403);
        }

        return $this->repondre(function () use ($request) {
            $resultat = $this->salles->reserver($this->moi(), $request->toArray());
            $this->logger->info('Salle réservée', ['par' => $this->moi(), 'salle' => $resultat['salle']]);

            return $resultat;
        });
    }

    #[Route('/api/annuler', name: 'annuler', methods: ['POST'])]
    public function annuler(Request $request): JsonResponse
    {
        if (!$this->csrfValide($request)) {
            return $this->json(['message' => 'Token CSRF invalide, rechargez la page.'], 403);
        }

        return $this->repondre(function () use ($request) {
            $donnees = $request->toArray();
            $this->salles->annuler($this->moi(), (string) ($donnees['uid'] ?? ''), (string) ($donnees['commentaire'] ?? ''));
            $this->logger->info('Réservation de salle annulée', ['par' => $this->moi()]);

            return ['ok' => true];
        });
    }

    #[Route('/api/salles/rafraichir', name: 'rafraichir', methods: ['POST'])]
    public function rafraichir(Request $request): JsonResponse
    {
        if (!$this->csrfValide($request)) {
            return $this->json(['message' => 'Token CSRF invalide, rechargez la page.'], 403);
        }

        return $this->repondre(fn () => ['salles' => count($this->salles->salles(true))]);
    }

    private function repondre(callable $action): JsonResponse
    {
        try {
            return $this->json($action());
        } catch (GraphException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->logger->error('Réservation de salles : ' . $e->getMessage(), ['exception' => $e]);

            return $this->json(['message' => 'Microsoft Graph est injoignable ou a renvoyé une erreur inattendue.'], 502);
        }
    }

    private function csrfValide(Request $request): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken('salles', (string) $request->headers->get('X-CSRF-TOKEN')));
    }

    private function moi(): string
    {
        $user = $this->getUser();

        return strtolower((string) ($user instanceof User ? $user->getEmail() : $user?->getUserIdentifier()));
    }
}
