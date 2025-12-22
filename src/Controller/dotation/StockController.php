<?php

namespace App\Controller\dotation;

use App\Entity\dotation\Article;
use App\Entity\dotation\Couleur;
use App\Entity\dotation\Stock;
use App\Entity\dotation\AssociationTaillesArticle;
use App\Entity\dotation\AssociationCouleursArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StockController extends AbstractController
{
    #[Route('/dota/stock', name: 'app_stock_dota')]
    public function stock_dota(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $listeArticles = $entityManager->getRepository(Article::class)->findAll();
        $listeCouleurs = $entityManager->getRepository(Couleur::class)->findAll();

        $produitsAvecDetails = [];

        foreach ($listeArticles as $article) {
            $tailles = $entityManager->getRepository(AssociationTaillesArticle::class)->findBy(['article' => $article]);
            $assoCouleurs = $entityManager->getRepository(AssociationCouleursArticle::class)->findBy(['article' => $article]);
            $stocks = $entityManager->getRepository(Stock::class)->findBy(['referenceArticle' => $article->getReference()]);
            $stockDetails = [];
            
            foreach ($tailles as $tailleAssoc) {
                foreach ($assoCouleurs as $assoCouleur) {
                    $stock = array_filter($stocks, function ($s) use ($tailleAssoc, $assoCouleur) {
                        $tailleNom = $tailleAssoc->getTaille()?->getNom();
                        $couleurNom = $assoCouleur->getCouleur()?->getNom();
                        return $s->getNomTaille() === $tailleNom && $s->getNomCouleur() === $couleurNom;
                    });
                    $couleur = $assoCouleur->getCouleur();

                    $stockDetails[] = [
                        'taille' => $tailleAssoc->getTaille()?->getNom(),
                        'couleur' => $assoCouleur->getCouleur()?->getNom(),
                        'stock' => $stock ? reset($stock)->getStock() : 0,
                        'codeCouleur' => $couleur ? $couleur->getCodeCouleur() : null,
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

    #[Route('/dota/stock/update', name: 'update_stock', methods: ['POST'])]
    public function updateStock(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $referenceArticle = $request->request->get('referenceArticle');
        $nomTaille = $request->request->get('nomTaille');
        $nomCouleur = $request->request->get('nomCouleur');
        $quantity = (int) $request->request->get('quantity');
    
        $stock = $entityManager->getRepository(Stock::class)->findOneBy([
            'referenceArticle' => $referenceArticle,
            'nomTaille' => $nomTaille,
            'nomCouleur' => $nomCouleur,
        ]);
    
        if ($stock) {
            $stock->setStock($quantity);
        } else {
            $stock = new Stock();
            $stock->setReferenceArticle($referenceArticle);
            $stock->setNomTaille($nomTaille);
            $stock->setNomCouleur($nomCouleur);
            $stock->setStock($quantity);
            $entityManager->persist($stock);
        }
    
        $entityManager->flush();
        return $this->redirectToRoute('app_stock_dota');
    }
}