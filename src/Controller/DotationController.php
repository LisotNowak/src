<?php
// src/Controller/DotationController.php
namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\EventService;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use DateTime;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class DotationController extends AbstractController
{
    #[Route('/dota', name: 'app_index_dota')]
    public function index_dota(EntityManagerInterface $entityManager): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié

            $listProducts = $entityManager->getRepository(Product::class)->findAll();

            return $this->render('dotation/index.html.twig', [
                'listProducts' => $listProducts,
            ]);
        }

        return $this->redirectToRoute('app_accueil');
        
    }

    #[Route('/dota/article', name: 'app_article_dota')]
    public function article_dota(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié

            

            if($request->request->get('id') == ""){
                // Récupérer le paramètre "id" depuis l'URL
                $id = $request->query->get('id'); // Ceci récupère l'ID passé en paramètre dans l'URL
            }else{
                // Récupérer les paramètres "id" et "nbpanier" depuis la requête POST
                $id = $request->request->get('id'); // Paramètre id envoyé en POST
            }

            $panier = $session->get('cart', []); 
            $nombreArticles = count($panier); 

            $product = $entityManager->getRepository(Product::class)->find($id);

            return $this->render('dotation/productpage.html.twig', [
                'product' => $product,
                'nombreArticles' => $nombreArticles,
            ]);
        }
        return $this->redirectToRoute('app_accueil');
        
    }

    #[Route('/dota/addToCart', name: 'add_to_cart', methods: ['POST'])]
    public function addToCart(EntityManagerInterface $entityManager, Request $request, SessionInterface $session): Response
    {
        // Récupérer les informations envoyées par la requête
        $productId = $request->request->get('product_id'); // Identifiant du produit
        $quantity = $request->request->get('quantity', 1); // Quantité (par défaut 1)
        $size = $request->request->get('size'); // Taille du produit

        // Récupérer le panier actuel ou initialiser un tableau vide
        $cart = $session->get('cart', []);

        // Vérifier si l'article est déjà dans le panier
        if (isset($cart[$productId])) {
            // Si le produit est déjà dans le panier, on ajuste la quantité
            $cart[$productId]['quantity'] += $quantity;
        } else {
            // Sinon, on ajoute le produit avec sa quantité et taille
            $cart[$productId] = [
                'id' => $productId,
                'quantite' => $quantity,
                'nom' => $entityManager->getRepository(Product::class)->find($productId)->getNom(),
                'reference' => $entityManager->getRepository(Product::class)->find($productId)->getReference(),
                'description' => $entityManager->getRepository(Product::class)->find($productId)->getDescription(),
                'prix' => $entityManager->getRepository(Product::class)->find($productId)->getPrix(),
                'taille' => $size
            ];
        }

        // Sauvegarder le panier dans la session
        $session->set('cart', $cart);

        $panier = $session->get('cart', []); 
        $nombreArticles = count($panier); 

        return $this->redirectToRoute('app_article_dota', [
            'id' => $productId,        // Paramètre id
            'nbpannier' => $nombreArticles,   // Paramètre nbpannier
        ]);

        return new Response('OK', Response::HTTP_OK);
    }

    #[Route('/dota/panier', name: 'app_panier_dota')]
    public function panier_dota(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié

            

            $panier = $session->get('cart', []); 
            $nombreArticles = count($panier); 

            // $product = $entityManager->getRepository(Product::class)->find($id);

            return $this->render('dotation/panier.html.twig', [
                'panier' => $panier,
                'nombreArticles' => $nombreArticles,
            ]);
        }
        return $this->redirectToRoute('app_accueil');
        
    }


}
