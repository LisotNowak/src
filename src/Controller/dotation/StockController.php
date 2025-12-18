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