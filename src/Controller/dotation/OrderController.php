<?php

namespace App\Controller\dotation;

use App\Entity\dotation\Article;
use App\Entity\dotation\Type;
use App\Entity\dotation\Commande;
use App\Entity\dotation\Stock;
use App\Entity\dotation\AssociationCommandeArticle;
use App\Entity\dotation\AssociationTaillesArticle;
use App\Entity\dotation\AssociationCouleursArticle;
use App\Entity\dotation\Taille;
use App\Entity\dotation\Couleur;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class OrderController extends AbstractController
{
    #[Route('/dota/validerPanier', name: 'valider_panier', methods: ['POST'])]
    public function validerPanier(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }

        $user = $this->getUser();
        $panier = $session->get('cart', []);

        if (!$user || empty($panier)) {
            $this->addFlash('error', 'Erreur : le panier est vide ou l\'utilisateur n\'est pas connecté.');
            return $this->redirectToRoute('app_panier_dota');
        }

        $targetId = $session->get('target_user_id');
        $targetUser = null;
        if ($this->isGranted('ROLE_ADMIN') && $targetId) {
            $targetUser = $entityManager->getRepository(User::class)->find($targetId);
        }

        date_default_timezone_set('Europe/Paris');

        $commande = new Commande();
        $commande->setUserMail($targetUser ? $targetUser->getEmail() : $user->getEmail());
        $commande->setDate((new \DateTime())->format('Y-m-d H:i:s'));
        $commande->setNomEtat('Validée');

        $entityManager->persist($commande);
        $entityManager->flush();

        foreach ($panier as $item) {
            $article = $entityManager->getRepository(Article::class)->find($item['id']);
            if (!$article) {
                continue;
            }
            $tailleEntity = $entityManager->getRepository(Taille::class)->findOneBy(['nom' => $item['taille']]);
            $couleurEntity = $entityManager->getRepository(Couleur::class)->findOneBy(['nom' => $item['couleur']]);

            $associationCommandeArticle = new AssociationCommandeArticle();
            $associationCommandeArticle->setCommande($commande);
            $associationCommandeArticle->setArticle($article);
            $associationCommandeArticle->setTaille($tailleEntity);
            $associationCommandeArticle->setCouleur($couleurEntity);
            $associationCommandeArticle->setNb($item['quantite']);

            $entityManager->persist($associationCommandeArticle);
        }

        $entityManager->flush();

        $session->remove('cart');
        if ($targetUser) {
            $session->remove('target_user_id');
        }

        $this->addFlash('success', 'Votre panier a été validé avec succès.');
        return $this->redirectToRoute('app_panier_dota');
    }

    #[Route('/dota/mes-commandes', name: 'app_mes_commandes_dota')]
    public function mesCommandes(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADM_DOTA') && !$this->isGranted('ROLE_USER_DOTA')) {
            return $this->redirectToRoute('app_accueil');
        }
        
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_accueil');
        }

        $selectedTypeId = (int) $request->query->get('type', 0);
        $selectedEtat = $request->query->get('etat', '');

        $types = $entityManager->getRepository(Type::class)->findBy([], ['nom' => 'ASC']);

        $qb = $entityManager->createQueryBuilder();
        $qb->select('DISTINCT c.nomEtat')
           ->from(Commande::class, 'c')
           ->orderBy('c.nomEtat', 'ASC');
        $etatsRes = $qb->getQuery()->getArrayResult();
        $etats = array_map(fn($r) => $r['nomEtat'], $etatsRes);

        $criteria = ['userMail' => $user->getEmail()];
        if ($selectedEtat !== '') {
            $criteria['nomEtat'] = $selectedEtat;
        }
        $commandesEntities = $entityManager->getRepository(Commande::class)
            ->findBy($criteria, ['date' => 'DESC']);

        $selectedTypeEntity = $selectedTypeId > 0 ? $entityManager->getRepository(Type::class)->find($selectedTypeId) : null;

        $commandes = [];
        foreach ($commandesEntities as $commande) {
            $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
                ->findBy(['commande' => $commande]);

            $items = [];
            foreach ($assocs as $assoc) {
                $article = $assoc->getArticle();
                if (!$article) {
                    continue;
                }

                if ($selectedTypeEntity) {
                    // Article stores `nomType` as string; compare by name
                    $articleNomType = $article->getNomType();
                    if ($articleNomType !== $selectedTypeEntity->getNom()) {
                        continue;
                    }
                }

                $items[] = [
                    'article' => $article,
                    'taille' => $assoc->getTaille()?->getNom(),
                    'couleur' => $assoc->getCouleur()?->getNom(),
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
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');


        $buildList = function(string $etat) use ($entityManager) {
            $commandesEntities = $entityManager->getRepository(Commande::class)
                ->findBy(['nomEtat' => $etat], ['date' => 'DESC']);

            $commandes = [];
            foreach ($commandesEntities as $commande) {
                $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
                    ->findBy(['commande' => $commande]);

                $items = [];
                foreach ($assocs as $assoc) {
                    $article = $assoc->getArticle();

                    $stockDisponible = 0;
                    if ($article) {
                        $stockEntity = $entityManager->getRepository(Stock::class)->findOneBy([
                            'referenceArticle' => $article->getReference(),
                            'nomTaille' => $assoc->getTaille()?->getNom(),
                            'nomCouleur' => $assoc->getCouleur()?->getNom(),
                        ]);
                        $stockDisponible = $stockEntity ? (int) $stockEntity->getStock() : 0;
                    }

                    $items[] = [
                        'article' => $article,
                        'taille' => $assoc->getTaille()?->getNom(),
                        'couleur' => $assoc->getCouleur()?->getNom(),
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

        $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)->findBy(['commande' => $commande]);
        if (empty($assocs)) {
            $this->addFlash('error', 'Aucune ligne trouvée pour cette commande.');
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $errors = [];
        foreach ($assocs as $assoc) {
            $article = $assoc->getArticle();
            $articleId = $article ? $article->getId() : 'n/a';
            if (!$article) {
                $errors[] = "Article introuvable (ID {$articleId}).";
                continue;
            }

            $stock = $entityManager->getRepository(Stock::class)->findOneBy([
                'referenceArticle' => $article->getReference(),
                'nomTaille' => $assoc->getTaille()?->getNom(),
                'nomCouleur' => $assoc->getCouleur()?->getNom(),
            ]);

            if (!$stock) {
                $errors[] = sprintf('Pas de fiche stock pour %s (%s / %s).', $article->getNom(), $assoc->getTaille()?->getNom(), $assoc->getCouleur()?->getNom());
                continue;
            }

            if ($stock->getStock() < $assoc->getNb()) {
                $errors[] = sprintf('Stock insuffisant pour %s %s/%s : demandé %d, disponible %d.',
                    $article->getNom(), $assoc->getTaille()?->getNom(), $assoc->getCouleur()?->getNom(), $assoc->getNb(), $stock->getStock());
            }
        }

        if (!empty($errors)) {
            foreach ($errors as $err) {
                $this->addFlash('error', $err);
            }
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        foreach ($assocs as $assoc) {
            $article = $assoc->getArticle();
            $stock = $entityManager->getRepository(Stock::class)->findOneBy([
                'referenceArticle' => $article->getReference(),
                'nomTaille' => $assoc->getTaille()?->getNom(),
                'nomCouleur' => $assoc->getCouleur()?->getNom(),
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

        $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)
            ->findBy(['commande' => $commande]);

        $cart = [];

        foreach ($assocs as $assoc) {
            $article = $assoc->getArticle();
            if (!$article) {
                continue;
            }

            $tailleNom = $assoc->getTaille()?->getNom();
            $couleurNom = $assoc->getCouleur()?->getNom();

            $cartKey = $article->getId() . '_' . $tailleNom . '_' . $couleurNom;

            $cart[$cartKey] = [
                'id' => $article->getId(),
                'quantite' => $assoc->getNb(),
                'nom' => $article->getNom(),
                'reference' => $article->getReference(),
                'description' => $article->getDescription(),
                'prix' => $article->getPrix(),
                'taille' => $tailleNom,
                'couleur' => $couleurNom,
                'point' => $article->getPoint(),
                'image' => $article->getImage(),
            ];
        }

        $session->set('cart', $cart);

        $this->addFlash('success', 'La commande a été rechargée dans votre panier.');

        return $this->redirectToRoute('app_panier_dota');
    }

    #[Route('/dota/commande/{id}/edit', name: 'app_commande_edit', methods: ['GET', 'POST'])]
    public function editCommande(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
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
                if ($request->request->has('delete_item')) {
                    $assocIdToDelete = $request->request->get('delete_item');
                    $assocToDelete = $entityManager->getRepository(AssociationCommandeArticle::class)->find($assocIdToDelete);
                    if ($assocToDelete && $assocToDelete->getCommande()?->getId() === $commande->getId()) {
                        $entityManager->remove($assocToDelete);
                        $entityManager->flush();
                        $this->addFlash('success', 'L\'article a été supprimé de la commande.');
                        return $this->redirectToRoute('app_commande_edit', ['id' => $id]);
                    }
                }

                $itemsData = $request->request->all('items');
                $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)->findBy(['commande' => $commande]);

                foreach ($assocs as $assoc) {
                    $assocId = $assoc->getId();
                    if (isset($itemsData[$assocId])) {
                        $data = $itemsData[$assocId];
                        $newQuantity = (int)($data['quantite'] ?? 0);

                        if ($newQuantity > 0) {
                            $assoc->setNb($newQuantity);
                            if (!empty($data['taille'])) {
                                $tailleEntity = $entityManager->getRepository(Taille::class)->findOneBy(['nom' => $data['taille']]);
                                $assoc->setTaille($tailleEntity);
                            }
                            if (!empty($data['couleur'])) {
                                $couleurEntity = $entityManager->getRepository(Couleur::class)->findOneBy(['nom' => $data['couleur']]);
                                $assoc->setCouleur($couleurEntity);
                            }
                            $entityManager->persist($assoc);
                        } else {
                            $entityManager->remove($assoc);
                        }
                    }
                }

                $newItems = $request->request->all('new_items');
                foreach ($newItems as $newItemData) {
                    $articleId = $newItemData['article'] ?? null;
                    $quantity = (int)($newItemData['quantite'] ?? 0);
                    $taille = $newItemData['taille'] ?? null;
                    $couleur = $newItemData['couleur'] ?? null;

                    if ($articleId && $quantity > 0 && $taille && $couleur) {
                        $newAssoc = new AssociationCommandeArticle();
                        $articleEntity = $entityManager->getRepository(Article::class)->find($articleId);
                        $tailleEntity = $entityManager->getRepository(Taille::class)->findOneBy(['nom' => $taille]);
                        $couleurEntity = $entityManager->getRepository(Couleur::class)->findOneBy(['nom' => $couleur]);

                        $newAssoc->setCommande($commande);
                        $newAssoc->setArticle($articleEntity);
                        $newAssoc->setNb($quantity);
                        $newAssoc->setTaille($tailleEntity);
                        $newAssoc->setCouleur($couleurEntity);
                        $entityManager->persist($newAssoc);
                    }
                }

                $entityManager->flush();
                $this->addFlash('success', 'La commande a été mise à jour.');
            }
            return $this->redirectToRoute('app_gestion_commandes_dota');
        }

        $assocs = $entityManager->getRepository(AssociationCommandeArticle::class)->findBy(['commande' => $commande]);
        $itemsDetails = [];
        foreach ($assocs as $assoc) {
            $article = $assoc->getArticle();
            $taillesDispo = $entityManager->getRepository(AssociationTaillesArticle::class)->findBy(['article' => $article]);
            $couleursDispo = $entityManager->getRepository(AssociationCouleursArticle::class)->findBy(['article' => $article]);

            $itemsDetails[] = [
                'assoc' => $assoc,
                'article' => $article,
                'taillesDisponibles' => array_map(fn($t) => $t->getTaille()?->getNom(), $taillesDispo),
                'couleursDisponibles' => array_map(fn($c) => $c->getCouleur()?->getNom(), $couleursDispo),
            ];
        }

        $allArticles = $entityManager->getRepository(Article::class)->findBy([], ['nom' => 'ASC']);

        return $this->render('dotation/editCommande.html.twig', [
            'commande' => $commande,
            'itemsDetails' => $itemsDetails,
            'allArticles' => $allArticles,
        ]);
    }

    #[Route('/commande/{id}/attente', name: 'app_commande_mettre_en_attente', methods: ['POST'])]
    public function mettreEnAttente(Commande $commande, EntityManagerInterface $em, Request $request): Response
    {
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
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isCsrfTokenValid('gestion_commande_' . $commande->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token invalide');
        }

        $commande->setNomEtat('Validée');
        $em->flush();

        $this->addFlash('success', 'Commande réactivée et repassée à l\'état Validée.');
        return $this->redirectToRoute('app_gestion_commandes_dota');
    }
}