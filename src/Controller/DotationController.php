<?php
// src/Controller/DotationController.php
namespace App\Controller;

use App\Entity\dotation\Article;
use App\Entity\dotation\Type;
use App\Entity\dotation\Taille;
use App\Entity\dotation\Couleur;
use App\Entity\dotation\AssociationCouleursArticle;
use App\Entity\dotation\AssociationTaillesArticle;

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

    #[Route('/dota/article', name: 'get_article', methods: ['POST'])]
    public function getArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        $id = $request->request->get('id');

        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            return $this->json(['error' => 'Article non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Récupérer les tailles et couleurs associées
        $tailles = $entityManager->getRepository(AssociationTaillesArticle::class)->findBy(['idArticle' => $id]);
        $couleurs = $entityManager->getRepository(AssociationCouleursArticle::class)->findBy(['idArticle' => $id]);

        

        $tableauTailles = array_map(fn($t) => $entityManager->getRepository(Taille::class)->findOneByNom($t->getNomTaille())->getId(), $tailles);
        $tableauCouleurs = array_map(fn($c) => $entityManager->getRepository(Couleur::class)->findOneByNom($c->getNomCouleur())->getId(), $couleurs);


        $data = [
            'reference' => $article->getReference(),
            'nom' => $article->getNom(),
            'prix' => $article->getPrix(),
            'point' => $article->getPoint(),
            'descriptions' => $article->getDescription(),
            'type' => $entityManager->getRepository(Type::class)->findOneByNom($article->getNomType())->getId(),
            'tableauTailles' => $tableauTailles,
            'tableauCouleurs' => $tableauCouleurs,
        ];

        return $this->json($data);
    }

    #[Route('/dota/article/delete/{id}', name: 'delete_article', methods: ['GET', 'DELETE'])]
    public function deleteArticle(int $id, EntityManagerInterface $entityManager): Response
    {
        $article = $entityManager->getRepository(Article::class)->find($id);
    
        if (!$article) {
            return new Response('Article non trouvé', Response::HTTP_NOT_FOUND);
        }
    
        // Suppression de l'image si elle existe
        $imageName = $article->getImage();
        if ($imageName) {
            $imagePath = $this->getParameter('images_directory') . $imageName;

            var_dump($imagePath);

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
    
        $entityManager->remove($article);
        $entityManager->flush();
    
        return $this->redirectToRoute('app_admin_dota');
    }
    


    #[Route('/dota/article/save', name: 'save_article', methods: ['POST'])]
    public function saveArticle(Request $request, EntityManagerInterface $entityManager): Response
    {
        $id = $request->request->get('id');
        $reference = $request->request->get('reference');
        $nom = $request->request->get('nom');
        $prix = $request->request->get('prix');
        $point = $request->request->get('point');
        $description = $request->request->get('description');
        $typeId = $request->request->get('produit-type');
        $tailleIds = $request->request->all('produit-taille');
        $couleurIds = $request->request->all('produit-couleur');

        if ($id) {
            $article = $entityManager->getRepository(Article::class)->find($id);
            if (!$article) {
                return new Response('Article non trouvé', Response::HTTP_NOT_FOUND);
            }
        } else {
            $article = new Article();
        }

        $article->setReference($reference);
        $article->setNom($nom);
        $article->setPrix($prix);
        $article->setPoint($point);
        $article->setDescription($description);

        // Type
        if ($typeId) {
            $type = $entityManager->getRepository(Type::class)->find($typeId);
            if ($type) {
                $article->setNomType($type->getNom());
            }
        }

        // ✅ Gestion de l'image uploadée
        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = preg_replace('/[^a-zA-Z0-9-_]/', '_', strtolower($originalFilename));
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
            

            try {
                $imageFile->move(
                    $this->getParameter('images_directory'), // à définir dans services.yaml
                    $newFilename
                );
                $article->setImage($newFilename);
            } catch (FileException $e) {
                return new Response('Erreur lors de l’upload de l’image.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $entityManager->persist($article);
        $entityManager->flush();

        // Assoc Tailles
        foreach ($tailleIds as $tailleId) {
            $taille = $entityManager->getRepository(Taille::class)->find($tailleId);
            if ($taille) {
                $assocTaille = new AssociationTaillesArticle();
                $assocTaille->setIdArticle($article->getId());
                $assocTaille->setNomTaille($taille->getNom());
                $entityManager->persist($assocTaille);
            }
        }

        // Assoc Couleurs
        foreach ($couleurIds as $couleurId) {
            $couleur = $entityManager->getRepository(Couleur::class)->find($couleurId);
            if ($couleur) {
                $assocCouleur = new AssociationCouleursArticle();
                $assocCouleur->setIdArticle($article->getId());
                $assocCouleur->setNomCouleur($couleur->getNom());
                $entityManager->persist($assocCouleur);
            }
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_admin_dota');
    }


    #[Route('/dota', name: 'app_index_dota')]
    public function index_dota(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié

            $listeArticles = $entityManager->getRepository(Article::class)->findAll();
            $listeAssociationTaillesArticle = $entityManager->getRepository(AssociationTaillesArticle::class)->findAll();
            $panier = $session->get('cart', []); 
            $nombreArticles = count($panier); 

            return $this->render('dotation/index.html.twig', [
                'listeArticles' => $listeArticles,
                'nombreArticles' => $nombreArticles,
                'listeAssociationTaillesArticle' => $listeAssociationTaillesArticle,
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
        $listeAssociationTaillesArticle = $entityManager->getRepository(AssociationTaillesArticle::class)->findAll();


        return $this->render('dotation/index.html.twig', [
            'listeArticles' => $listeArticles,
            'nombreArticles' => $nombreArticles,
            'listeAssociationTaillesArticle' => $listeAssociationTaillesArticle,

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

    #[Route('/dota/updateCart', name: 'update_cart', methods: ['POST'])]
public function updateCart(Request $request, SessionInterface $session): Response
{
    $productId = $request->request->get('product_id');
    $size = $request->request->get('size');
    $quantity = $request->request->get('quantity');

    $cart = $session->get('cart', []);

    if (isset($cart[$productId . $size])) {
        if ($quantity > 0) {
            $cart[$productId . $size]['quantite'] = $quantity;
        } else {
            unset($cart[$productId . $size]); // Supprime l'article si la quantité est 0
        }
    }

    $session->set('cart', $cart);

    return $this->redirectToRoute('app_panier_dota');
}

#[Route('/dota/removeFromCart', name: 'remove_from_cart', methods: ['POST'])]
public function removeFromCart(Request $request, SessionInterface $session): Response
{
    $productId = $request->request->get('product_id');
    $size = $request->request->get('size');

    $cart = $session->get('cart', []);

    if (isset($cart[$productId . $size])) {
        unset($cart[$productId . $size]); // Supprime l'article
    }

    $session->set('cart', $cart);

    return $this->redirectToRoute('app_panier_dota');
}

}
