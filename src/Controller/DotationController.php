<?php

// src/Controller/DotationController.php
namespace App\Controller;

use App\Entity\dotation\Article;
use App\Entity\dotation\Type;
use App\Entity\dotation\Taille;
use App\Entity\dotation\Couleur;
use App\Entity\dotation\Stock;
use App\Entity\dotation\Commande;
use App\Entity\dotation\AssociationCommandeArticle;
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

            $listeArticles = $entityManager->getRepository(Article::class)->findBy([], ['nomType' => 'ASC', 'nom' => 'ASC']);
            $listeCouleurs = $entityManager->getRepository(Couleur::class)->findAll();

            $listeAssociationTaillesArticle = $entityManager->getRepository(AssociationTaillesArticle::class)->findAll();
            $listeAssociationCouleursArticle = $entityManager->getRepository(AssociationCouleursArticle::class)->findAll();
            $panier = $session->get('cart', []); 
            $nombreArticles = count($panier); 

            // Calcul des points présents dans le panier (point * quantite)
            $pointsInCart = 0;
            foreach ($panier as $item) {
                $qty = isset($item['quantite']) ? (int) $item['quantite'] : 1;
                $pt  = isset($item['point']) ? (int) $item['point'] : 0;
                $pointsInCart += $pt * $qty;
            }

            return $this->render('dotation/index.html.twig', [
                'listeArticles' => $listeArticles,
                'listeCouleurs' => $listeCouleurs,
                'nombreArticles' => $nombreArticles,
                'pointsInCart' => $pointsInCart,
                'listeAssociationTaillesArticle' => $listeAssociationTaillesArticle,
                'listeAssociationCouleursArticle' => $listeAssociationCouleursArticle,
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

    #[Route('/dota/stock', name: 'app_stock_dota')]
    public function stock_dota(EntityManagerInterface $entityManager): Response
    {
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $listeArticles = $entityManager->getRepository(Article::class)->findAll();
            $listeCouleurs = $entityManager->getRepository(Couleur::class)->findAll();
            $couleur = "";

            $produitsAvecDetails = [];
    
            foreach ($listeArticles as $article) {
                $tailles = $entityManager->getRepository(AssociationTaillesArticle::class)->findBy(['idArticle' => $article->getId()]);
                $assoCouleurs = $entityManager->getRepository(AssociationCouleursArticle::class)->findBy(['idArticle' => $article->getId()]);
                $stocks = $entityManager->getRepository(Stock::class)->findBy(['referenceArticle' => $article->getReference()]);
                $stockDetails = [];
                foreach ($tailles as $taille) {
                    foreach ($assoCouleurs as $assoCouleur) {
                        $stock = array_filter($stocks, function ($s) use ($taille, $assoCouleur) {
                            return $s->getNomTaille() === $taille->getNomTaille() && $s->getNomCouleur() === $assoCouleur->getNomCouleur();
                        });
                        $couleur = $entityManager->getRepository(Couleur::class)->findOneBy(['nom' => $assoCouleur->getNomCouleur()]);
           
                        $stockDetails[] = [
                            'taille' => $taille->getNomTaille(),
                            'couleur' => $assoCouleur->getNomCouleur(),
                            'stock' => $stock ? reset($stock)->getStock() : 0,
                            'codeCouleur' => $couleur->getCodeCouleur(),
                        ];
                    }
                }
    
                $produitsAvecDetails[] = [
                    'article' => $article,
                    'stockDetails' => $stockDetails,
                ];
            }
    
            return $this->render('dotation/stock.html.twig', [
                'produitsAvecDetails' => $produitsAvecDetails,
            ]);
        }
    
        return $this->redirectToRoute('app_accueil');
    }
    

    #[Route('/dota/stock/update', name: 'update_stock', methods: ['POST'])]
    public function updateStock(Request $request, EntityManagerInterface $entityManager): Response
    {
        $referenceArticle = $request->request->get('referenceArticle');
        $nomTaille = $request->request->get('nomTaille');
        $nomCouleur = $request->request->get('nomCouleur');
        $quantity = (int) $request->request->get('quantity');
    
        // Rechercher l'entité Stock correspondante
        $stock = $entityManager->getRepository(Stock::class)->findOneBy([
            'referenceArticle' => $referenceArticle,
            'nomTaille' => $nomTaille,
            'nomCouleur' => $nomCouleur,
        ]);
    
        if ($stock) {
            // Mettre à jour la quantité
            $stock->setStock($quantity);
        } else {
            // Si aucune entrée n'existe, en créer une nouvelle
            $stock = new Stock();
            $stock->setReferenceArticle($referenceArticle);
            $stock->setNomTaille($nomTaille);
            $stock->setNomCouleur($nomCouleur);
            $stock->setStock($quantity);
            $entityManager->persist($stock);
        }
    
        // Sauvegarder les modifications
        $entityManager->flush();
    
        return $this->redirectToRoute('app_stock_dota');
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
        $productId = $request->request->get('product_id');
        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $size = $request->request->get('size');
        $color = $request->request->get('color');

        $user = $this->getUser();
        $userPoints = $user ? (int) $user->getPointDotation() : 0;

        $cart = $session->get('cart', []);

        // calcul des points déjà dans le panier
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
        // Vérifiez si l'utilisateur est déjà authentifié
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            // Redirigez l'utilisateur s'il est déjà authentifié

            $panier = $session->get('cart', []); 
            $nombreArticles = count($panier); 

            // Enrichir chaque item du panier avec le nomType de l'article (pour le regroupement)
            $enriched = [];
            foreach ($panier as $item) {
                $articleEntity = $entityManager->getRepository(Article::class)->find($item['id']);
                $item['nomType'] = $articleEntity ? $articleEntity->getNomType() : 'Autre';
                $enriched[] = $item;
            }

            // Grouper les articles par type
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
        return $this->redirectToRoute('app_accueil');
        
    }

    #[Route('/dota/updateCart', name: 'update_cart', methods: ['POST'])]
public function updateCart(Request $request, SessionInterface $session): Response
{
    $productId = $request->request->get('product_id');
    $size = $request->request->get('size');
    $color = $request->request->get('color');
    $quantity = (int) $request->request->get('quantity');

    $cart = $session->get('cart', []);
    $cartKey = $productId . '_' . $size . '_' . $color;

    // Vérifier points si on augmente la quantité
    if (isset($cart[$cartKey]) && $quantity > 0) {
        $user = $this->getUser();
        $userPoints = $user ? (int)$user->getPointDotation() : 0;

        // calcul points courants hors ligne pour cet item
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

    // mise à jour habituelle
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
    $productId = $request->request->get('product_id');
    $size = $request->request->get('size');
    $color = $request->request->get('color'); // Ajout de la couleur

    $cart = $session->get('cart', []);

    $cartKey = $productId . '_' . $size . '_' . $color; // Clé harmonisée

    if (isset($cart[$cartKey])) {
        unset($cart[$cartKey]); // Supprime l'article
    }

    $session->set('cart', $cart);

    return $this->redirectToRoute('app_panier_dota');
}

#[Route('/dota/validerPanier', name: 'valider_panier', methods: ['POST'])]
public function validerPanier(SessionInterface $session, EntityManagerInterface $entityManager): Response
{
    $user = $this->getUser();
    $panier = $session->get('cart', []);

    if (!$user || empty($panier)) {
        $this->addFlash('error', 'Erreur : le panier est vide ou l’utilisateur n’est pas connecté.');
        return $this->redirectToRoute('app_panier_dota');
    }

    date_default_timezone_set('Europe/Paris');

    // Créer une nouvelle commande
    $commande = new Commande();
    $commande->setUserMail($user->getEmail());
    $commande->setDate((new \DateTime())->format('Y-m-d H:i:s'));
    $commande->setNomEtat('Validée');

    $entityManager->persist($commande);
    $entityManager->flush(); // Nécessaire pour obtenir l'ID de la commande

    // Ajouter les articles du panier à la commande via AssociationCommandeArticle
    foreach ($panier as $item) {
        $associationCommandeArticle = new AssociationCommandeArticle();
        $associationCommandeArticle->setIdCommande($commande->getId());
        $associationCommandeArticle->setIdArticle($item['id']);
        $associationCommandeArticle->setNomTaille($item['taille']);
        $associationCommandeArticle->setNomCouleur($item['couleur']);
        $associationCommandeArticle->setNb($item['quantite']);

        $entityManager->persist($associationCommandeArticle);
    }

    // Sauvegarder la commande et ses associations
    $entityManager->flush();

    // Vider le panier
    $session->remove('cart');

    $this->addFlash('success', 'Votre panier a été validé avec succès.');
    return $this->redirectToRoute('app_panier_dota');
}

    #[Route('/dota/mes-commandes', name: 'app_mes_commandes_dota')]
    public function mesCommandes(EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_accueil');
        }

        $user = $this->getUser();
        $email = $user ? $user->getEmail() : null;
        if (!$email) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_index_dota');
        }

        $commandesEntities = $entityManager->getRepository(Commande::class)
            ->findBy(['userMail' => $email], ['date' => 'DESC']);

        $commandes = [];
        foreach ($commandesEntities as $commande) {
            $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
                ->findBy(['idCommande' => $commande->getId()]);

            $items = [];
            foreach ($assocs as $assoc) {
                $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());
                $items[] = [
                    'article' => $article,
                    'taille' => $assoc->getNomTaille(),
                    'couleur' => $assoc->getNomCouleur(),
                    'quantite' => $assoc->getNb(),
                ];
            }

            $commandes[] = [
                'commande' => $commande,
                'items' => $items,
            ];
        }

        return $this->render('dotation/commandes.html.twig', [
            'commandes' => $commandes,
        ]);
    }

}
