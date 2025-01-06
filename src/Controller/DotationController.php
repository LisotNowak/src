<?php
// src/Controller/DotationController.php
namespace App\Controller;

use App\Entity\dotation\Article;
use App\Entity\dotation\Type;
use App\Entity\dotation\Taille;
use App\Entity\dotation\Couleur;
// use App\Entity\Article;
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
    public function index_dota(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié

            $listeArticles = $entityManager->getRepository(Article::class)->findAll();
            $panier = $session->get('cart', []); 
            $nombreArticles = count($panier); 

            return $this->render('dotation/index.html.twig', [
                'listeArticles' => $listeArticles,
                'nombreArticles' => $nombreArticles,
            ]);
        }

        return $this->redirectToRoute('app_accueil');
        
    }

    #[Route('/dota/admin', name: 'app_admin_dota')]
    public function admin_dota(EntityManagerInterface $entityManager): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié

            $listeArticles = $entityManager->getRepository(Article::class)->findAll();
            $listeTypes = $entityManager->getRepository(Type::class)->findAll();
            $listeTailles = $entityManager->getRepository(Taille::class)->findAll();
            $listeCouleurs = $entityManager->getRepository(Couleur::class)->findAll();

            return $this->render('dotation/admin.html.twig', [
                'listeArticles' => $listeArticles,
                'listeTypes' => $listeTypes,
                'listeTailles' => $listeTailles,
                'listeCouleurs' => $listeCouleurs,
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

            $product = $entityManager->getRepository(Article::class)->find($id);

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
        if (isset($cart[$productId.$size])) {
            if ($cart[$productId.$size]['taille'] == $size) {
                // Si le produit est déjà dans le panier avec la meme taille, on ajuste la quantité
                $cart[$productId.$size]['quantite'] += $quantity;
            }
            
        } else {
            // Sinon, on ajoute le produit avec sa quantité et taille
            $cart[$productId.$size] = [
                'id' => $productId,
                'quantite' => $quantity,
                'nom' => $entityManager->getRepository(Article::class)->find($productId)->getNom(),
                'reference' => $entityManager->getRepository(Article::class)->find($productId)->getReference(),
                'description' => $entityManager->getRepository(Article::class)->find($productId)->getDescription(),
                'prix' => $entityManager->getRepository(Article::class)->find($productId)->getPrix(),
                'taille' => $size,
                'point' => $entityManager->getRepository(Article::class)->find($productId)->getPoint()
            ];
        }

        // Sauvegarder le panier dans la session
        $session->set('cart', $cart);

        $listeArticles = $entityManager->getRepository(Article::class)->findAll();
        $panier = $session->get('cart', []); 
        $nombreArticles = count($panier); 

        return $this->render('dotation/index.html.twig', [
            'listeArticles' => $listeArticles,
            'nombreArticles' => $nombreArticles,
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

            // $product = $entityManager->getRepository(Article::class)->find($id);

            return $this->render('dotation/panier.html.twig', [
                'panier' => $panier,
                'nombreArticles' => $nombreArticles,
            ]);
        }
        return $this->redirectToRoute('app_accueil');
        
    }


}
