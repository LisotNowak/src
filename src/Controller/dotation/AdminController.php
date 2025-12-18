<?php

namespace App\Controller\dotation;

use App\Entity\dotation\Type;
use App\Entity\dotation\Taille;
use App\Entity\dotation\Couleur;
use App\Entity\dotation\Article;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\dotation\AssociationTaillesArticle;
use App\Entity\dotation\AssociationCouleursArticle;

class AdminController extends AbstractController
{
    #[Route('/dota', name: 'app_index_dota')]
    public function index_dota(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

        $listeArticles = $entityManager->getRepository(Article::class)->findAll();
        $listeCouleurs = $entityManager->getRepository(Couleur::class)->findAll();

        $listeAssociationTaillesArticle = $entityManager->getRepository(AssociationTaillesArticle::class)->findAll();
        $listeAssociationCouleursArticle = $entityManager->getRepository(AssociationCouleursArticle::class)->findAll();
        $listeTypes = $entityManager->getRepository(Type::class)->findBy([], ['id' => 'ASC']);
        $panier = $session->get('cart', []);
        $nombreArticles = count($panier);

        $pointsInCart = 0;
        foreach ($panier as $item) {
            $qty = isset($item['quantite']) ? (int) $item['quantite'] : 1;
            $pt  = isset($item['point']) ? (int) $item['point'] : 0;
            $pointsInCart += $pt * $qty;
        }

        $params = [
            'listeArticles' => $listeArticles,
            'listeCouleurs' => $listeCouleurs,
            'nombreArticles' => $nombreArticles,
            'listeTypes' => $listeTypes,
            'pointsInCart' => $pointsInCart,
            'listeAssociationTaillesArticle' => $listeAssociationTaillesArticle,
            'listeAssociationCouleursArticle' => $listeAssociationCouleursArticle,
        ];

        if ($this->isGranted('ROLE_ADMIN')) {
            $params['listeUsers'] = $entityManager->getRepository(User::class)->findAll();
        }

        return $this->render('dotation/index.html.twig', $params);
    }

    #[Route('/dota/admin', name: 'app_admin_dota')]
    public function admin_dota(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $listeArticles = $entityManager->getRepository(Article::class)->findAll();
        $listeTypes = $entityManager->getRepository(Type::class)->findAll();
        $listeTailles = $entityManager->getRepository(Taille::class)->findAll();
        $listeCouleurs = $entityManager->getRepository(Couleur::class)->findAll();

        return $this->render('dotation/admin.html.twig', [
            'listeTypes' => $listeTypes,
            'listeTailles' => $listeTailles,
            'listeCouleurs' => $listeCouleurs,
            'listeArticles' => $listeArticles,
            'active_link' => 'admin'
        ]);
    }

    #[Route('/dota/admin/update-points', name: 'admin_update_points', methods: ['POST'])]
    public function updateUserPoints(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $newPoints = $data['points'] ?? null;

        if (!$userId || $newPoints === null) {
            return new JsonResponse(['success' => false, 'message' => 'Paramètres manquants'], 400);
        }

        $user = $entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur introuvable'], 404);
        }

        $user->setPointDotation((int) $newPoints);
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'points' => $user->getPointDotation()]);
    }

    #[Route('/admin/update-points', name: 'admin_update_points_full', methods: ['POST'])]
    public function updatePoints(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $data = json_decode($request->getContent(), true);
        $user = $userRepository->find($data['user_id'] ?? 0);

        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé']);
        }

        if (isset($data['points'])) {
            $user->setPointDotation((int)$data['points']);
        }

        if (!empty($data['date_debut'])) {
            $user->setDateDebutContrat(new \DateTime($data['date_debut']));
        } else {
            $user->setDateDebutContrat(null);
        }

        if (!empty($data['date_fin'])) {
            $user->setDateFinContrat(new \DateTime($data['date_fin']));
        } else {
            $user->setDateFinContrat(null);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/dota/service/role', name: 'app_dota_service_role', methods: ['POST'])]
    public function setRoleForService(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $token = $data['_csrf_token'] ?? '';
        if (!$this->isCsrfTokenValid('service_role_action', $token)) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        $service = $data['service'] ?? null;
        $action = $data['action'] ?? null;

        if (!$service || !in_array($action, ['grant', 'revoke'])) {
            return new JsonResponse(['success' => false, 'message' => 'Paramètres invalides'], 400);
        }

        $users = $userRepository->findBy(['service' => $service]);
        $modified = 0;

        foreach ($users as $user) {
            $roles = $user->getRoles();
            if ($action === 'grant') {
                if (!in_array('ROLE_USER_DOTA', $roles, true)) {
                    $roles[] = 'ROLE_USER_DOTA';
                    $user->setRoles($roles);
                    $em->persist($user);
                    $modified++;
                }
            } else {
                if (in_array('ROLE_USER_DOTA', $roles, true)) {
                    $roles = array_values(array_filter($roles, fn($r) => $r !== 'ROLE_USER_DOTA'));
                    $user->setRoles($roles);
                    $em->persist($user);
                    $modified++;
                }
            }
        }

        if ($modified > 0) {
            $em->flush();
        }

        return new JsonResponse(['success' => true, 'modified' => $modified]);
    }
}