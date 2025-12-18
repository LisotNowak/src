<?php

namespace App\Controller\dotation;

use App\Entity\dotation\Article;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartController extends AbstractController
{
    #[Route('/dota/set-target-user', name: 'set_target_user', methods: ['POST'])]
    public function setTargetUser(Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false], 403);
        }

        $data = json_decode($request->getContent(), true);
        $targetId = $data['target_user_id'] ?? null;

        if (!$targetId) {
            $session->remove('target_user_id');
            return $this->json(['success' => true]);
        }

        $user = $entityManager->getRepository(User::class)->find($targetId);
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur introuvable'], 404);
        }

        $session->set('target_user_id', $targetId);
        return $this->json(['success' => true]);
    }

    #[Route('/dota/addToCart', name: 'add_to_cart', methods: ['POST'])]
    public function addToCart(EntityManagerInterface $entityManager, Request $request, SessionInterface $session): Response
    {
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

        $productId = $request->request->get('product_id');
        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $size = $request->request->get('size');
        $color = $request->request->get('color');

        $targetUser = null;
        if ($this->isGranted('ROLE_ADMIN')) {
            $targetId = $request->request->get('target_user_id') ?? $session->get('target_user_id');
            if ($targetId) {
                $targetUser = $entityManager->getRepository(User::class)->find($targetId);
                if ($targetUser) {
                    $session->set('target_user_id', $targetId);
                }
            }
        }

        $user = $this->getUser();
        $userPoints = $targetUser ? (int)$targetUser->getPointDotation() : ($user ? (int) $user->getPointDotation() : 0);

        $cart = $session->get('cart', []);

        $pointsInCart = 0;
        foreach ($cart as $it) {
            $qtyIt = isset($it['quantite']) ? (int)$it['quantite'] : 1;
            $ptIt  = isset($it['point']) ? (int)$it['point'] : 0;
            $pointsInCart += $qtyIt * $ptIt;
        }

        $product = $entityManager->getRepository(Article::class)->find($productId);
        if (!$product) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Produit introuvable.'], 404);
            }
            $this->addFlash('error', 'Produit introuvable.');
            return $this->redirectToRoute('app_index_dota');
        }

        $productPoint = (int) $product->getPoint();
        $potentialPoints = $pointsInCart + ($productPoint * $quantity);
        if ($potentialPoints > $userPoints) {
            $remaining = max($userPoints - $pointsInCart, 0);
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ajout impossible : points insuffisants.',
                    'pointsInCart' => $pointsInCart,
                    'remaining' => $remaining,
                    'userPoints' => $userPoints,
                ], 400);
            }
            $this->addFlash('error', "Ajout impossible : il vous reste {$remaining} points.");
            return $this->redirectToRoute('app_index_dota');
        }

        $cartKey = $productId . '_' . $size . '_' . $color;

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantite'] += $quantity;
        } else {
            $cart[$cartKey] = [
                'id' => $productId,
                'quantite' => $quantity,
                'nom' => $product->getNom(),
                'reference' => $product->getReference(),
                'description' => $product->getDescription(),
                'prix' => $product->getPrix(),
                'taille' => $size,
                'couleur' => $color,
                'point' => $product->getPoint(),
                'image' => $product->getImage(),
            ];
        }

        $session->set('cart', $cart);

        if ($request->isXmlHttpRequest()) {
            $nombreArticles = count($cart);
            $pointsInCart = 0;
            foreach ($cart as $item) {
                $qty = isset($item['quantite']) ? (int) $item['quantite'] : 1;
                $pt  = isset($item['point']) ? (int) $item['point'] : 0;
                $pointsInCart += $pt * $qty;
            }
            return $this->json([
                'success' => true,
                'nombreArticles' => $nombreArticles,
                'pointsInCart' => $pointsInCart,
            ]);
        }

        return $this->redirectToRoute('app_panier_dota');
    }

    #[Route('/dota/panier', name: 'app_panier_dota')]
    public function panier_dota(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

        $panier = $session->get('cart', []);
        $nombreArticles = count($panier);

        $enriched = [];
        foreach ($panier as $item) {
            $articleEntity = $entityManager->getRepository(Article::class)->find($item['id']);
            $item['nomType'] = $articleEntity ? $articleEntity->getNomType() : 'Autre';
            $enriched[] = $item;
        }

        $panierGrouped = [];
        foreach ($enriched as $it) {
            $type = $it['nomType'] ?? 'Autre';
            $panierGrouped[$type][] = $it;
        }

        return $this->render('dotation/panier.html.twig', [
            'panierGrouped' => $panierGrouped,
            'panier' => $panier,
            'nombreArticles' => $nombreArticles,
        ]);
    }

    #[Route('/dota/updateCart', name: 'update_cart', methods: ['POST'])]
    public function updateCart(Request $request, SessionInterface $session): Response
    {
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

        $productId = $request->request->get('product_id');
        $size = $request->request->get('size');
        $color = $request->request->get('color');
        $quantity = (int) $request->request->get('quantity');

        $cart = $session->get('cart', []);
        $cartKey = $productId . '_' . $size . '_' . $color;

        if (isset($cart[$cartKey]) && $quantity > 0) {
            $user = $this->getUser();
            $userPoints = $user ? (int)$user->getPointDotation() : 0;

            $pointsInCart = 0;
            foreach ($cart as $key => $it) {
                if ($key === $cartKey) continue;
                $pointsInCart += (isset($it['quantite'])?(int)$it['quantite']:1) * (isset($it['point'])?(int)$it['point']:0);
            }

            $itemPoint = isset($cart[$cartKey]['point']) ? (int)$cart[$cartKey]['point'] : 0;
            $potential = $pointsInCart + ($itemPoint * $quantity);
            if ($potential > $userPoints) {
                $this->addFlash('error', 'Impossible : points insuffisants pour cette quantité.');
                return $this->redirectToRoute('app_panier_dota');
            }
        }

        if (isset($cart[$cartKey])) {
            if ($quantity > 0) {
                $cart[$cartKey]['quantite'] = $quantity;
            } else {
                unset($cart[$cartKey]);
            }
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('app_panier_dota');
    }

    #[Route('/dota/removeFromCart', name: 'remove_from_cart', methods: ['POST'])]
    public function removeFromCart(Request $request, SessionInterface $session): Response
    {
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }
        
        $productId = $request->request->get('product_id');
        $size = $request->request->get('size');
        $color = $request->request->get('color');

        $cart = $session->get('cart', []);
        $cartKey = $productId . '_' . $size . '_' . $color;

        if (isset($cart[$cartKey])) {
            unset($cart[$cartKey]);
        }

        $session->set('cart', $cart);
        return $this->redirectToRoute('app_panier_dota');
    }
}