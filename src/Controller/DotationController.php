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
use App\Entity\User;
use App\Repository\UserRepository; 
use App\Entity\dotation\DemandeEchange;

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
use Symfony\Component\HttpFoundation\JsonResponse;

class DotationController extends AbstractController
{

    #[Route('/dota/article', name: 'get_article', methods: ['POST'])]
    public function getArticle(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $id = $request->request->get('id');
        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        // Récupération des tailles associées
        $taillesAssoc = $entityManager->getRepository(AssociationTaillesArticle::class)
            ->findBy(['idArticle' => $id]);
        $taillesNoms = array_map(fn($assoc) => $assoc->getNomTaille(), $taillesAssoc);

        // Récupération des couleurs associées
        $couleursAssoc = $entityManager->getRepository(AssociationCouleursArticle::class)
            ->findBy(['idArticle' => $id]);
        $couleursNoms = array_map(fn($assoc) => $assoc->getNomCouleur(), $couleursAssoc);

        // Retour JSON adapté à ton JS
        return new JsonResponse([
            'id' => $article->getId(),
            'reference' => $article->getReference(),
            'nom' => $article->getNom(),
            'prix' => $article->getPrix(),
            'point' => $article->getPoint(),
            'descriptions' => $article->getDescription(),
            'nomType' => $article->getNomType(), // ✅ on renvoie le nom du type, pas un id
            'tableauTailles' => $taillesNoms,
            'tableauCouleurs' => $couleursNoms,
        ]);
    }


    #[Route('/dota/article/delete/{id}', name: 'delete_article', methods: ['GET', 'DELETE'])]
    public function deleteArticle(int $id, EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

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
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $id = $request->request->get('id');
        $reference = $request->request->get('reference');
        $nom = $request->request->get('nom');
        $prix = $request->request->get('prix');
        $point = $request->request->get('point');
        $description = $request->request->get('description');
        $typeNom = $request->request->get('produit-type');
        $tailleNoms = $request->request->all('produit-taille');
        $couleurNoms = $request->request->all('produit-couleur');

        // ✅ Création ou modification
        $isNew = !$id;
        if ($id) {
            $article = $entityManager->getRepository(Article::class)->find($id);
            if (!$article) {
                return new Response('Article non trouvé', Response::HTTP_NOT_FOUND);
            }
        } else {
            $article = new Article();
        }

        // ✅ Mise à jour des champs
        $article->setReference($reference);
        $article->setNom($nom);
        $article->setPrix($prix);
        $article->setPoint($point);
        $article->setDescription($description);
        if ($typeNom) {
            $article->setNomType($typeNom);
        }

        // ✅ Gestion de l'image
        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = preg_replace('/[^a-zA-Z0-9-_]/', '_', strtolower($originalFilename));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move($this->getParameter('images_directory'), $newFilename);
                $article->setImage($newFilename);
            } catch (FileException $e) {
                return new Response('Erreur lors de l’upload de l’image.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // ✅ Sauvegarde de base de l’article
        $entityManager->persist($article);
        $entityManager->flush();

        // ✅ Suppression des anciennes associations
        $entityManager->createQuery('DELETE FROM ' . AssociationTaillesArticle::class . ' a WHERE a.idArticle = :id')
            ->setParameter('id', $article->getId())
            ->execute();

        $entityManager->createQuery('DELETE FROM ' . AssociationCouleursArticle::class . ' c WHERE c.idArticle = :id')
            ->setParameter('id', $article->getId())
            ->execute();

        // ✅ Réassociation des tailles
        foreach ($tailleNoms as $tailleNom) {
            $taille = $entityManager->getRepository(Taille::class)->findOneBy(['nom' => $tailleNom]);
            if ($taille) {
                $assocTaille = new AssociationTaillesArticle();
                $assocTaille->setIdArticle($article->getId());
                $assocTaille->setNomTaille($taille->getNom());
                $entityManager->persist($assocTaille);
            }
        }

        // ✅ Réassociation des couleurs
        foreach ($couleurNoms as $couleurNom) {
            $couleur = $entityManager->getRepository(Couleur::class)->findOneBy(['nom' => $couleurNom]);
            if ($couleur) {
                $assocCouleur = new AssociationCouleursArticle();
                $assocCouleur->setIdArticle($article->getId());
                $assocCouleur->setNomCouleur($couleur->getNom());
                $entityManager->persist($assocCouleur);
            }
        }

        $entityManager->flush();

        // ✅ Message flash de confirmation
        $this->addFlash(
            'success',
            $isNew ? '✅ Article ajouté avec succès.' : '✏️ Article modifié avec succès.'
        );

        return $this->redirectToRoute('app_admin_dota');
    }


    #[Route('/dota', name: 'app_index_dota')]
    public function index_dota(EntityManagerInterface $entityManager, SessionInterface $session): Response
    {
            // Autoriser ROLE_ADM_DOTA OU ROLE_USER_DOTA
            if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
                return $this->redirectToRoute('app_accueil');
            }

            $listeArticles = $entityManager->getRepository(Article::class)->findBy([], ['nomType' => 'ASC', 'nom' => 'ASC']);
            $listeCouleurs = $entityManager->getRepository(Couleur::class)->findAll();

            $listeAssociationTaillesArticle = $entityManager->getRepository(AssociationTaillesArticle::class)->findAll();
            $listeAssociationCouleursArticle = $entityManager->getRepository(AssociationCouleursArticle::class)->findAll();
            $listeTypes = $entityManager->getRepository(Type::class)->findAll();
            $panier = $session->get('cart', []); 
            $nombreArticles = count($panier); 

            // Calcul des points présents dans le panier (point * quantite)
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

            // si admin, fournir la liste des utilisateurs pour le select "commander pour"
            if ($this->isGranted('ROLE_ADMIN')) {
                $params['listeUsers'] = $entityManager->getRepository(User::class)->findAll();
            }

            return $this->render('dotation/index.html.twig', $params);
        
    }

    #[Route('/dota/admin', name: 'app_admin_dota')]
    public function admin_dota(EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
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

    #[Route('/dota/admin/exchanges', name: 'app_admin_manage_exchanges', methods: ['GET'])]
    public function manageExchanges(EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Accès refusé.');
            return $this->redirectToRoute('app_index_dota');
        }

        $demandes = $entityManager->getRepository(DemandeEchange::class)->findBy([], ['dateDemande' => 'DESC']);

        // On prépare les données pour le template
        $demandesDetails = [];
        foreach ($demandes as $demande) {
            $oldAssociation = $demande->getOldAssociationCommandeArticle();
            $oldArticle = null;
            if ($oldAssociation) {
                $oldArticle = $entityManager->getRepository(Article::class)->find($oldAssociation->getIdArticle());
            }

            $demandesDetails[] = [
                'demande' => $demande,
                'oldArticle' => $oldArticle,
            ];
        }

        return $this->render('dotation/manage_exchanges.html.twig', [
            'demandesDetails' => $demandesDetails,
            'active_link' => 'gestion_echanges'
        ]);
    }

    #[Route('/dota/admin/exchange/{id}/update', name: 'app_admin_update_exchange', methods: ['POST'])]
    public function updateExchangeStatus(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Accès refusé.');
            return $this->redirectToRoute('app_admin_manage_exchanges');
        }

        $demande = $entityManager->getRepository(DemandeEchange::class)->find($id);
        $status = $request->request->get('status');

        if (!$demande || !in_array($status, ['Approuvée', 'Refusée'])) {
            $this->addFlash('danger', 'Demande ou statut invalide.');
            return $this->redirectToRoute('app_admin_manage_exchanges');
        }

        // Si la demande est approuvée, on gère la logique de stock et de commande
        if ($status === 'Approuvée') {
            $newArticle = $demande->getNewArticle();
            $newTaille = $demande->getNewTaille();
            $newCouleur = $demande->getNewCouleur();

            // 1. Vérifier et décrémenter le stock du nouvel article
            $stockNewItem = $entityManager->getRepository(Stock::class)->findOneBy([
                'referenceArticle' => $newArticle->getReference(),
                'nomTaille' => $newTaille,
                'nomCouleur' => $newCouleur,
            ]);

            if (!$stockNewItem || $stockNewItem->getStock() <= 0) {
                $this->addFlash('danger', 'Stock insuffisant pour l\'article ' . $newArticle->getNom() . ' (Taille: ' . $newTaille . ', Couleur: ' . $newCouleur . '). L\'échange ne peut pas être approuvé.');
                return $this->redirectToRoute('app_admin_manage_exchanges');
            }
            $stockNewItem->setStock($stockNewItem->getStock() - 1);
            $entityManager->persist($stockNewItem);

            // 2. Incrémenter le stock de l'article retourné
            $oldAssoc = $demande->getOldAssociationCommandeArticle();
            $oldArticle = $entityManager->getRepository(Article::class)->find($oldAssoc->getIdArticle());
            
            if ($oldArticle) {
                $stockOldItem = $entityManager->getRepository(Stock::class)->findOneBy([
                    'referenceArticle' => $oldArticle->getReference(),
                    'nomTaille' => $oldAssoc->getNomTaille(),
                    'nomCouleur' => $oldAssoc->getNomCouleur(),
                ]);
    
                if ($stockOldItem) {
                    $stockOldItem->setStock($stockOldItem->getStock() + 1);
                    $entityManager->persist($stockOldItem);
                }
            }
            // Optionnel: Gérer le cas où l'article retourné n'a plus de ligne de stock

            // 3. Créer une nouvelle commande pour l'échange
            $commandeEchange = new Commande();
            $commandeEchange->setUserMail($demande->getUser()->getEmail());
            $commandeEchange->setDate((new \DateTime())->format('Y-m-d H:i:s'));
            $commandeEchange->setNomEtat('Echange Approuvé');
            $entityManager->persist($commandeEchange);

            // Flush ici pour obtenir l'ID de la nouvelle commande
            $entityManager->flush();

            // 4. Créer l'association pour la nouvelle commande
            $assocEchange = new AssociationCommandeArticle();
            $assocEchange->setIdCommande($commandeEchange->getId());
            $assocEchange->setIdArticle($newArticle->getId());
            $assocEchange->setNb(1);
            $assocEchange->setNomTaille($newTaille);
            $assocEchange->setNomCouleur($newCouleur);
            $entityManager->persist($assocEchange);

            $this->addFlash('success', 'L\'échange a été approuvé. Le stock a été mis à jour et une commande a été créée.');

        } else { // Si la demande est refusée
            $this->addFlash('info', 'La demande d\'échange a été refusée.');
        }

        // Mettre à jour le statut de la demande dans tous les cas
        $demande->setStatus($status);
        $entityManager->flush();

        return $this->redirectToRoute('app_admin_manage_exchanges');
    }

    #[Route('/dota/point', name: 'app_point_dota')]
    public function point_dota(EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

            $listeUsers = $entityManager->getRepository(User::class)->findAll();
                
            // Récupérer les services uniques
            $services = array_unique(array_map(fn($u) => $u->getService(), $listeUsers));
            sort($services);
            
            // Créer un map des sections par service
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

        return $this->redirectToRoute('app_accueil');

    }

    #[Route('/dota/stock', name: 'app_stock_dota')]
    public function stock_dota(EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if ($this->isGranted('ROLE_ADM_DOTA') && $this->isGranted('ROLE_USER_DOTA')) {
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
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

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
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

            

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

    #[Route('/dota/set-target-user', name: 'set_target_user', methods: ['POST'])]
    public function setTargetUser(Request $request, SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
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

        // Autoriser ROLE_ADM_DOTA OU ROLE_USER_DOTA
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

        $productId = $request->request->get('product_id');
        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $size = $request->request->get('size');
        $color = $request->request->get('color');

        // Si admin, on peut travailler au nom d'un autre utilisateur (target_user_id)
        $targetUser = null;
        if ($this->isGranted('ROLE_ADMIN')) {
            $targetId = $request->request->get('target_user_id') ?? $session->get('target_user_id');
            if ($targetId) {
                $targetUser = $entityManager->getRepository(User::class)->find($targetId);
                if ($targetUser) {
                    // mémoriser en session (si fourni via fetch ou select)
                    $session->set('target_user_id', $targetId);
                }
            }
        }

        $user = $this->getUser();
        // points à utiliser : ceux de la cible si existante ET valide, sinon points de l'utilisateur connecté
        $userPoints = $targetUser ? (int)$targetUser->getPointDotation() : ($user ? (int) $user->getPointDotation() : 0);

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
            // Autoriser ROLE_ADM_DOTA OU ROLE_USER_DOTA
            if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
                return $this->redirectToRoute('app_accueil');
            }

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

    #[Route('/dota/updateCart', name: 'update_cart', methods: ['POST'])]
    public function updateCart(Request $request, SessionInterface $session): Response
    {
        // Autoriser ROLE_ADM_DOTA OU ROLE_USER_DOTA
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

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
        // Autoriser ROLE_ADM_DOTA OU ROLE_USER_DOTA
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }
        
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
        // Autoriser ROLE_ADM_DOTA OU ROLE_USER_DOTA
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

        $user = $this->getUser();
        $panier = $session->get('cart', []);

        if (!$user || empty($panier)) {
            $this->addFlash('error', 'Erreur : le panier est vide ou l’utilisateur n’est pas connecté.');
            return $this->redirectToRoute('app_panier_dota');
        }

        // Si admin et une cible en session, on place la commande pour cet utilisateur
        $targetId = $session->get('target_user_id');
        $targetUser = null;
        if ($this->isGranted('ROLE_ADMIN') && $targetId) {
            $targetUser = $entityManager->getRepository(User::class)->find($targetId);
        }

        date_default_timezone_set('Europe/Paris');

        // Créer une nouvelle commande
        $commande = new Commande();
        $commande->setUserMail($targetUser ? $targetUser->getEmail() : $user->getEmail());
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

        // Vider le panier et éventuellement la cible
        $session->remove('cart');
        if ($targetUser) {
            $session->remove('target_user_id');
        }

        $this->addFlash('success', 'Votre panier a été validé avec succès.');
        return $this->redirectToRoute('app_panier_dota');
    }


    #[Route('/dota/mes-demandes-echange', name: 'app_mes_demandes_echange')]
        public function mesDemandesEchange(EntityManagerInterface $entityManager): Response
        {
            // protection ROLE_ADM_DOTA
            $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

            $user = $this->getUser();
            if (!$user) {
                return $this->redirectToRoute('app_accueil');
            }

            // Récupérer toutes les demandes de l'utilisateur
            $demandesEntities = $entityManager->getRepository(DemandeEchange::class)
                ->findBy(['user' => $user], ['dateDemande' => 'DESC']);

            $demandes = [];

            foreach ($demandesEntities as $demande) {
                $oldAssoc = $demande->getOldAssociationCommandeArticle();
                $oldArticle = $oldAssoc 
                    ? $entityManager->getRepository(Article::class)->find($oldAssoc->getIdArticle()) 
                    : null;

                $newArticle = $demande->getNewArticle();

                $demandes[] = [
                    'demande' => $demande,
                    'oldAssoc' => $oldAssoc,
                    'oldArticle' => $oldArticle,
                    'newArticle' => $newArticle,
                ];
            }

            return $this->render('dotation/mesDemandesEchange.html.twig', [
                'demandes' => $demandes,
            ]);
        }



    #[Route('/dota/mes-commandes', name: 'app_mes_commandes_dota')]
    public function mesCommandes(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Autoriser ROLE_ADM_DOTA OU ROLE_USER_DOTA
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }
        
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_accueil');
        }

        $selectedTypeId = (int) $request->query->get('type', 0);
        $selectedEtat = $request->query->get('etat', '');

        // récupérer la liste des types pour le select
        $types = $entityManager->getRepository(Type::class)->findBy([], ['nom' => 'ASC']);

        // récupérer la liste des états distincts existants
        $qb = $entityManager->createQueryBuilder();
        $qb->select('DISTINCT c.nomEtat')
           ->from(Commande::class, 'c')
           ->orderBy('c.nomEtat', 'ASC');
        $etatsRes = $qb->getQuery()->getArrayResult();
        $etats = array_map(fn($r) => $r['nomEtat'], $etatsRes);

        // récupérer les commandes de l'utilisateur (on peut filtrer directement par état si demandé)
        $criteria = ['userMail' => $user->getEmail()];
        if ($selectedEtat !== '') {
            $criteria['nomEtat'] = $selectedEtat;
        }
        $commandesEntities = $entityManager->getRepository(Commande::class)
            ->findBy($criteria, ['date' => 'DESC']);

        $commandes = [];
        foreach ($commandesEntities as $commande) {
            $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
                ->findBy(['idCommande' => $commande->getId()]);

            $items = [];
            foreach ($assocs as $assoc) {
                $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());
                if (!$article) {
                    continue;
                }

                // si un filtre type produit est actif, ignorer les articles d'un autre type
                if ($selectedTypeId > 0) {
                    $articleType = $article->getType();
                    $articleTypeId = $articleType ? $articleType->getId() : 0;
                    if ($articleTypeId !== $selectedTypeId) {
                        continue;
                    }
                }

                $items[] = [
                    'article' => $article,
                    'taille' => $assoc->getNomTaille(),
                    'couleur' => $assoc->getNomCouleur(),
                    'quantite' => $assoc->getNb(),
                ];
            }

            if (count($items) === 0) {
                continue;
            }

            $commandes[] = [
                'commande' => $commande,
                'items' => $items,
            ];
        }

        return $this->render('dotation/mesCommandes.html.twig', [
            'commandes' => $commandes,
            'types' => $types,
            'selectedTypeId' => $selectedTypeId,
            'etats' => $etats,
            'selectedEtat' => $selectedEtat,
        ]);
    }

    #[Route('/dota/gestion-commandes', name: 'app_gestion_commandes_dota')]
    public function gestionCommandes(EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_accueil');
        }

        $buildList = function(string $etat) use ($entityManager) {
            $commandesEntities = $entityManager->getRepository(Commande::class)
                ->findBy(['nomEtat' => $etat], ['date' => 'DESC']);

            $commandes = [];
            foreach ($commandesEntities as $commande) {
                $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
                    ->findBy(['idCommande' => $commande->getId()]);

                $items = [];
                foreach ($assocs as $assoc) {
                    $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());

                    $stockDisponible = 0;
                    if ($article) {
                        $stockEntity = $entityManager->getRepository(Stock::class)->findOneBy([
                            'referenceArticle' => $article->getReference(),
                            'nomTaille' => $assoc->getNomTaille(),
                            'nomCouleur' => $assoc->getNomCouleur(),
                        ]);
                        $stockDisponible = $stockEntity ? (int) $stockEntity->getStock() : 0;
                    }

                    $items[] = [
                        'article' => $article,
                        'taille' => $assoc->getNomTaille(),
                        'couleur' => $assoc->getNomCouleur(),
                        'quantite' => $assoc->getNb(),
                        'stockDisponible' => $stockDisponible,
                    ];
                }

                if (count($items) === 0) {
                    continue;
                }

                $commandes[] = [
                    'commande' => $commande,
                    'items' => $items,
                ];
            }

            return $commandes;
        };

        $commandes_valide = $buildList('Validée');
        $commandes_sur_commande = $buildList('Sur commande');
        $commandes_attente = $buildList('En attente');

        return $this->render('dotation/gestionCommandes.html.twig', [
            'commandes_valide' => $commandes_valide,
            'commandes_sur_commande' => $commandes_sur_commande,
            'commandes_attente' => $commandes_attente,
        ]);

    }

    #[Route('/dota/commande/{id}/mettre-en-stock', name: 'app_commande_mettre_en_stock', methods: ['POST'])]
    public function mettreEnStock(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isCsrfTokenValid('gestion_commande_'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $commande = $entityManager->getRepository(Commande::class)->find($id);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)->findBy(['idCommande' => $commande->getId()]);
        if (empty($assocs)) {
            $this->addFlash('error', 'Aucune ligne trouvée pour cette commande.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $errors = [];
        // Vérification disponibilité sans modifier encore la base
        foreach ($assocs as $assoc) {
            $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());
            if (!$article) {
                $errors[] = "Article introuvable (ID {$assoc->getIdArticle()}).";
                continue;
            }

            $stock = $entityManager->getRepository(Stock::class)->findOneBy([
                'referenceArticle' => $article->getReference(),
                'nomTaille' => $assoc->getNomTaille(),
                'nomCouleur' => $assoc->getNomCouleur(),
            ]);

            if (!$stock) {
                $errors[] = sprintf('Pas de fiche stock pour %s (%s / %s).', $article->getNom(), $assoc->getNomTaille(), $assoc->getNomCouleur());
                continue;
            }

            if ($stock->getStock() < $assoc->getNb()) {
                $errors[] = sprintf('Stock insuffisant pour %s %s/%s : demandé %d, disponible %d.',
                    $article->getNom(), $assoc->getNomTaille(), $assoc->getNomCouleur(), $assoc->getNb(), $stock->getStock());
            }
        }

        if (!empty($errors)) {
            // Joindre plusieurs messages et les afficher
            foreach ($errors as $err) {
                $this->addFlash('error', $err);
            }
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        // Tous les checks OK -> décrémentation
        foreach ($assocs as $assoc) {
            $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());
            $stock = $entityManager->getRepository(Stock::class)->findOneBy([
                'referenceArticle' => $article->getReference(),
                'nomTaille' => $assoc->getNomTaille(),
                'nomCouleur' => $assoc->getNomCouleur(),
            ]);

            $stock->setStock($stock->getStock() - $assoc->getNb());
            $entityManager->persist($stock);
        }

        $commande->setNomEtat('Sur stock');
        $entityManager->persist($commande);
        $entityManager->flush();

        $this->addFlash('success', 'Commande passée en "Sur stock" et stocks mis à jour.');
        return $this->redirectToRoute('app_gestion_commandes_dota');
    }

    #[Route('/dota/commande/{id}/mettre-sur-commande', name: 'app_commande_mettre_sur_commande', methods: ['POST'])]
    public function mettreSurCommande(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isCsrfTokenValid('gestion_commande_'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $commande = $entityManager->getRepository(Commande::class)->find($id);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $commande->setNomEtat('Sur commande');
        $entityManager->persist($commande);
        $entityManager->flush();

        $this->addFlash('success', 'Commande passée en "Sur commande".');
        return $this->redirectToRoute('app_gestion_commandes_dota');
    }

    #[Route('/dota/ajax/get_user_points', name: 'ajax_get_user_points', methods: ['POST'])]
    public function getUserPoints(Request $request, EntityManagerInterface $entityManager, SessionInterface $session): JsonResponse
    {
        // protection ROLE_ADM_DOTA
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
    
    #[Route('/dota/commande/{id}/repasser', name: 'repasser_commande')]
    public function repasserCommande(
        int $id,
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ): Response {

        $commande = $entityManager->getRepository(Commande::class)->find($id);

        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_mes_commandes_dota');
        }

        // Récupération des lignes de la commande
        $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
            ->findBy(['idCommande' => $commande->getId()]);

        // Réinitialiser le panier
        $cart = [];

        foreach ($assocs as $assoc) {
            $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());
            if (!$article) {
                continue; // article supprimé
            }

            $cartKey = $article->getId() . '_' . $assoc->getNomTaille() . '_' . $assoc->getNomCouleur();

            $cart[$cartKey] = [
                'id' => $article->getId(),
                'quantite' => $assoc->getNb(),
                'nom' => $article->getNom(),
                'reference' => $article->getReference(),
                'description' => $article->getDescription(),
                'prix' => $article->getPrix(),
                'taille' => $assoc->getNomTaille(),
                'couleur' => $assoc->getNomCouleur(),
                'point' => $article->getPoint(),
                'image' => $article->getImage(),
            ];
        }

        $session->set('cart', $cart);

        $this->addFlash('success', 'La commande a été rechargée dans votre panier.');

        return $this->redirectToRoute('app_panier_dota');
    }

    #[Route('/dota/admin/update-points', name: 'admin_update_points', methods: ['POST'])]
    public function updateUserPoints(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // protection ROLE_ADM_DOTA
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

    #[Route('/dota/commande/{id}/edit', name: 'app_commande_edit', methods: ['GET', 'POST'])]
    public function editCommande(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $commande = $entityManager->getRepository(Commande::class)->find($id);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_commande_'.$commande->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
            } else {
                // Gérer la suppression d'un article
                if ($request->request->has('delete_item')) {
                    $assocIdToDelete = $request->request->get('delete_item');
                    $assocToDelete = $entityManager->getRepository(AssociationCommandeArticle::class)->find($assocIdToDelete);
                    if ($assocToDelete && $assocToDelete->getIdCommande() === $commande->getId()) {
                        $entityManager->remove($assocToDelete);
                        $entityManager->flush();
                        $this->addFlash('success', 'L\'article a été supprimé de la commande.');
                        return $this->redirectToRoute('app_commande_edit', ['id' => $id]);
                    }
                }

                // Gérer la mise à jour des articles existants
                $itemsData = $request->request->all('items');
                $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)->findBy(['idCommande' => $commande->getId()]);

                foreach ($assocs as $assoc) {
                    $assocId = $assoc->getId();
                    if (isset($itemsData[$assocId])) {
                        $data = $itemsData[$assocId];
                        $newQuantity = (int)($data['quantite'] ?? 0);

                        if ($newQuantity > 0) {
                            $assoc->setNb($newQuantity);
                            $assoc->setNomTaille($data['taille'] ?? $assoc->getNomTaille());
                            $assoc->setNomCouleur($data['couleur'] ?? $assoc->getNomCouleur());
                            $entityManager->persist($assoc);
                        } else {
                            // Si la quantité est 0, on supprime aussi
                            $entityManager->remove($assoc);
                        }
                    }
                }

                // Gérer l'ajout de nouveaux articles
                $newItems = $request->request->all('new_items');
                foreach ($newItems as $newItemData) {
                    $articleId = $newItemData['article'] ?? null;
                    $quantity = (int)($newItemData['quantite'] ?? 0);
                    $taille = $newItemData['taille'] ?? null;
                    $couleur = $newItemData['couleur'] ?? null;

                    if ($articleId && $quantity > 0 && $taille && $couleur) {
                        $newAssoc = new AssociationCommandeArticle();
                        $newAssoc->setIdCommande($commande->getId());
                        $newAssoc->setIdArticle($articleId);
                        $newAssoc->setNb($quantity);
                        $newAssoc->setNomTaille($taille);
                        $newAssoc->setNomCouleur($couleur);
                        $entityManager->persist($newAssoc);
                    }
                }

                $entityManager->flush();
                $this->addFlash('success', 'La commande a été mise à jour.');
            }
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        // Préparation des données pour le template
        $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)->findBy(['idCommande' => $commande->getId()]);
        $itemsDetails = [];
        foreach ($assocs as $assoc) {
            $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());
            $taillesDispo = $entityManager->getRepository(AssociationTaillesArticle::class)->findBy(['idArticle' => $assoc->getIdArticle()]);
            $couleursDispo = $entityManager->getRepository(AssociationCouleursArticle::class)->findBy(['idArticle' => $assoc->getIdArticle()]);

            $itemsDetails[] = [
                'assoc' => $assoc,
                'article' => $article,
                'taillesDisponibles' => array_map(fn($t) => $t->getNomTaille(), $taillesDispo),
                'couleursDisponibles' => array_map(fn($c) => $c->getNomCouleur(), $couleursDispo),
            ];
        }

        // Récupérer tous les articles pour le formulaire d'ajout
        $allArticles = $entityManager->getRepository(Article::class)->findBy([], ['nom' => 'ASC']);

        return $this->render('dotation/editCommande.html.twig', [
            'commande' => $commande,
            'itemsDetails' => $itemsDetails,
            'allArticles' => $allArticles,
        ]);
    }

    #[Route('/dota/exchange', name: 'app_exchange_dota', methods: ['GET'])]
    public function exchange_dota(EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        // Récupérer les articles des commandes passées de l'utilisateur pour le formulaire
        $commandesEntities = $entityManager->getRepository(Commande::class)
            ->findBy(['userMail' => $this->getUser()->getEmail()], ['date' => 'DESC']);

        $commandes = [];
        foreach ($commandesEntities as $commande) {
            $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
                ->findBy(['idCommande' => $commande->getId()]);

            $items = [];
            foreach ($assocs as $assoc) {
                $article = $entityManager->getRepository(Article::class)->find($assoc->getIdArticle());
                if (!$article) continue;

                $items[] = [
                    'assoc' => $assoc, // Pour avoir l'ID de la ligne de commande
                    'article' => $article,
                    'taille' => $assoc->getNomTaille(),
                    'couleur' => $assoc->getNomCouleur(),
                ];
            }

            if (!empty($items)) {
                $commandes[] = [
                    'commande' => $commande,
                    'items' => $items,
                ];
            }
        }

        // Récupérer tous les articles pour la sélection du nouvel article
        $allArticles = $entityManager->getRepository(Article::class)->findBy([], ['nom' => 'ASC']);

        return $this->render('dotation/exchange.html.twig', [
            'commandes' => $commandes,
            'allArticles' => $allArticles,
        ]);
    }

    #[Route('/dota/exchange/request', name: 'app_exchange_request', methods: ['POST'])]
    public function handleExchangeRequest(Request $request, EntityManagerInterface $entityManager): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $oldAssocId = $request->request->get('old_item_assoc_id');
        $newArticleId = $request->request->get('new_article_id');
        $newSize = $request->request->get('new_size');
        $newColor = $request->request->get('new_color');
        $reason = $request->request->get('reason');

        // Validation simple
        if (!$oldAssocId || !$newArticleId || !$newSize || !$newColor || !$reason) {
            $this->addFlash('danger', 'Tous les champs sont obligatoires.');
            return $this->redirectToRoute('app_exchange_dota');
        }

        $oldAssociation = $entityManager->getRepository(AssociationCommandeArticle::class)->find($oldAssocId);
        $newArticle = $entityManager->getRepository(Article::class)->find($newArticleId);

        if (!$oldAssociation || !$newArticle) {
            $this->addFlash('danger', 'Article invalide sélectionné.');
            return $this->redirectToRoute('app_exchange_dota');
        }

        // Créer la demande d'échange
        $demande = new DemandeEchange();
        $demande->setUser($user);
        $demande->setOldAssociationCommandeArticle($oldAssociation);
        $demande->setNewArticle($newArticle);
        $demande->setNewTaille($newSize);
        $demande->setNewCouleur($newColor);
        $demande->setReason($reason);
        $demande->setStatus('En attente'); // Statut initial

        $entityManager->persist($demande);
        $entityManager->flush();

        $this->addFlash('success', 'Votre demande d\'échange a bien été envoyée. Elle sera examinée par un administrateur.');

        return $this->redirectToRoute('app_exchange_dota');
    }

    #[Route('/admin/update-points', name: 'admin_update_points', methods: ['POST'])]
    public function updatePoints(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        // protection ROLE_ADM_DOTA
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

    #[Route('/commande/{id}/attente', name: 'app_commande_mettre_en_attente', methods: ['POST'])]
    public function mettreEnAttente(Commande $commande, EntityManagerInterface $em, Request $request): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isCsrfTokenValid('gestion_commande_' . $commande->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token invalide');
        }

        $commande->setNomEtat('En attente');
        $em->flush();

        $this->addFlash('success', 'Commande mise en attente.');
        return $this->redirectToRoute('app_gestion_commandes_dota');
    }

    #[Route('/commande/{id}/reactiver', name: 'app_commande_reactiver', methods: ['POST'])]
    public function reactiver(Commande $commande, EntityManagerInterface $em, Request $request): Response
    {
        // protection ROLE_ADM_DOTA
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isCsrfTokenValid('gestion_commande_' . $commande->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token invalide');
        }

        $commande->setNomEtat('Validée');
        $em->flush();

        $this->addFlash('success', 'Commande réactivée et repassée à l’état Validée.');
        return $this->redirectToRoute('app_gestion_commandes_dota');
    }

    #[Route('/dota/service/role', name: 'app_dota_service_role', methods: ['POST'])]
    public function setRoleForService(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        // protection ROLE_ADM_DOTA
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
        $action = $data['action'] ?? null; // 'grant' ou 'revoke'

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
            } else { // revoke
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
