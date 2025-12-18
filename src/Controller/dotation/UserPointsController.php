<?php

namespace App\Controller\dotation;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserPointsController extends AbstractController
{
    #[Route('/dota/point', name: 'app_point_dota')]
    public function point_dota(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $listeUsers = $entityManager->getRepository(User::class)->findBy([], ['nom' => 'ASC']);
            
        $services = array_unique(array_map(fn($u) => $u->getService(), $listeUsers));
        sort($services);
        
        $servicesSections = [];
        foreach ($services as $service) {
            $usersInService = array_filter($listeUsers, fn($u) => $u->getService() === $service);
            $sections = array_unique(array_map(fn($u) => $u->getSection(), $usersInService));
            sort($sections);
            $servicesSections[$service] = $sections;
        }
        
        return $this->render('dotation/point.html.twig', [
            'listeUsers' => $listeUsers,
            'services' => $services,
            'servicesSections' => $servicesSections,
        ]);
    }

    #[Route('/dota/ajax/get_user_points', name: 'ajax_get_user_points', methods: ['POST'])]
    public function getUserPoints(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        if (!$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non spécifié'], 400);
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur introuvable'], 404);
        }

        $pointsTotal = (int) $user->getPointDotation();

        $cart = $session->get('cart', []);
        $pointsInCart = 0;
        foreach ($cart as $item) {
            $pointsInCart += (int) $item['point'] * ((int) $item['quantite'] ?? 1);
        }

        $pointsRemaining = max($pointsTotal - $pointsInCart, 0);

        return new JsonResponse([
            'success' => true,
            'pointsTotal' => $pointsTotal,
            'pointsInCart' => $pointsInCart,
            'pointsRemaining' => $pointsRemaining,
        ]);
    }
}