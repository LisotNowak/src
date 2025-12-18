<?php

namespace App\Controller\dotation;

use App\Entity\dotation\Article;
use App\Entity\dotation\Commande;
use App\Entity\dotation\Stock;
use App\Entity\dotation\AssociationCommandeArticle;
use App\Entity\dotation\DemandeEchange;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExchangeController extends AbstractController
{
    #[Route('/dota/admin/exchanges', name: 'app_admin_manage_exchanges', methods: ['GET'])]
    public function manageExchanges(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Accès refusé.');
            return $this->redirectToRoute('app_index_dota');
        }

        $demandes = $entityManager->getRepository(DemandeEchange::class)->findBy([], ['dateDemande' => 'DESC']);

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

        if ($status === 'Approuvée') {
            $newArticle = $demande->getNewArticle();
            $newTaille = $demande->getNewTaille();
            $newCouleur = $demande->getNewCouleur();

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

            $commandeEchange = new Commande();
            $commandeEchange->setUserMail($demande->getUser()->getEmail());
            $commandeEchange->setDate((new \DateTime())->format('Y-m-d H:i:s'));
            $commandeEchange->setNomEtat('Echange Approuvé');
            $entityManager->persist($commandeEchange);

            $entityManager->flush();

            $assocEchange = new AssociationCommandeArticle();
            $assocEchange->setIdCommande($commandeEchange->getId());
            $assocEchange->setIdArticle($newArticle->getId());
            $assocEchange->setNb(1);
            $assocEchange->setNomTaille($newTaille);
            $assocEchange->setNomCouleur($newCouleur);
            $entityManager->persist($assocEchange);

            $this->addFlash('success', 'L\'échange a été approuvé. Le stock a été mis à jour et une commande a été créée.');

        } else {
            $this->addFlash('info', 'La demande d\'échange a été refusée.');
        }

        $demande->setStatus($status);
        $entityManager->flush();

        return $this->redirectToRoute('app_admin_manage_exchanges');
    }

    #[Route('/dota/mes-demandes-echange', name: 'app_mes_demandes_echange')]
    public function mesDemandesEchange(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_accueil');
        }

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

    #[Route('/dota/exchange', name: 'app_exchange_dota', methods: ['GET'])]
    public function exchange_dota(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if (!$this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

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
                    'assoc' => $assoc,
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

        $allArticles = $entityManager->getRepository(Article::class)->findBy([], ['nom' => 'ASC']);

        return $this->render('dotation/exchange.html.twig', [
            'commandes' => $commandes,
            'allArticles' => $allArticles,
        ]);
    }

    #[Route('/dota/exchange/request', name: 'app_exchange_request', methods: ['POST'])]
    public function handleExchangeRequest(Request $request, EntityManagerInterface $entityManager): Response
    {
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

        $demande = new DemandeEchange();
        $demande->setUser($user);
        $demande->setOldAssociationCommandeArticle($oldAssociation);
        $demande->setNewArticle($newArticle);
        $demande->setNewTaille($newSize);
        $demande->setNewCouleur($newColor);
        $demande->setReason($reason);
        $demande->setStatus('En attente');

        $entityManager->persist($demande);
        $entityManager->flush();

        $this->addFlash('success', 'Votre demande d\'échange a bien été envoyée. Elle sera examinée par un administrateur.');

        return $this->redirectToRoute('app_exchange_dota');
    }
}