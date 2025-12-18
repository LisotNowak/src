<?php

namespace App\Controller\dotation;

use App\Entity\dotation\Article;
use App\Entity\dotation\Taille;
use App\Entity\dotation\Couleur;
use App\Entity\dotation\AssociationTaillesArticle;
use App\Entity\dotation\AssociationCouleursArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class ArticleController extends AbstractController
{
    #[Route('/dota/article', name: 'get_article', methods: ['POST'])]
    public function getArticle(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $id = $request->request->get('id');
        $article = $entityManager->getRepository(Article::class)->find($id);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        $taillesAssoc = $entityManager->getRepository(AssociationTaillesArticle::class)
            ->findBy(['idArticle' => $id]);
        $taillesNoms = array_map(fn($assoc) => $assoc->getNomTaille(), $taillesAssoc);

        $couleursAssoc = $entityManager->getRepository(AssociationCouleursArticle::class)
            ->findBy(['idArticle' => $id]);
        $couleursNoms = array_map(fn($assoc) => $assoc->getNomCouleur(), $couleursAssoc);

        return new JsonResponse([
            'id' => $article->getId(),
            'reference' => $article->getReference(),
            'nom' => $article->getNom(),
            'prix' => $article->getPrix(),
            'point' => $article->getPoint(),
            'descriptions' => $article->getDescription(),
            'nomType' => $article->getNomType(),
            'tableauTailles' => $taillesNoms,
            'tableauCouleurs' => $couleursNoms,
        ]);
    }

    #[Route('/dota/article/delete/{id}', name: 'delete_article', methods: ['GET', 'DELETE'])]
    public function deleteArticle(int $id, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        $article = $entityManager->getRepository(Article::class)->find($id);
    
        if (!$article) {
            return new Response('Article non trouvé', Response::HTTP_NOT_FOUND);
        }
    
        $imageName = $article->getImage();
        if ($imageName) {
            $imagePath = $this->getParameter('images_directory') . $imageName;

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

        $isNew = !$id;
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
        if ($typeNom) {
            $article->setNomType($typeNom);
        }

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = preg_replace('/[^a-zA-Z0-9-_]/', '_', strtolower($originalFilename));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move($this->getParameter('images_directory'), $newFilename);
                $article->setImage($newFilename);
            } catch (\Exception $e) {
                return new Response('Erreur lors de l\'upload de l\'image.', Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $entityManager->persist($article);
        $entityManager->flush();

        $entityManager->createQuery('DELETE FROM ' . AssociationTaillesArticle::class . ' a WHERE a.idArticle = :id')
            ->setParameter('id', $article->getId())
            ->execute();

        $entityManager->createQuery('DELETE FROM ' . AssociationCouleursArticle::class . ' c WHERE c.idArticle = :id')
            ->setParameter('id', $article->getId())
            ->execute();

        foreach ($tailleNoms as $tailleNom) {
            $taille = $entityManager->getRepository(Taille::class)->findOneBy(['nom' => $tailleNom]);
            if ($taille) {
                $assocTaille = new AssociationTaillesArticle();
                $assocTaille->setIdArticle($article->getId());
                $assocTaille->setNomTaille($taille->getNom());
                $entityManager->persist($assocTaille);
            }
        }

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

        $this->addFlash(
            'success',
            $isNew ? '✅ Article ajouté avec succès.' : '✏️ Article modifié avec succès.'
        );

        return $this->redirectToRoute('app_admin_dota');
    }

    #[Route('/dota/article', name: 'app_article_dota')]
    public function article_dota(Request $request, EntityManagerInterface $entityManager, \Symfony\Component\HttpFoundation\Session\SessionInterface $session): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADM_DOTA');

        if ($request->request->get('id') == "") {
            $id = $request->query->get('id');
        } else {
            $id = $request->request->get('id');
        }

        $panier = $session->get('cart', []);
        $nombreArticles = count($panier);

        $product = $entityManager->getRepository(Article::class)->find($id);

        return $this->render('dotation/productpage.html.twig', [
            'product' => $product,
            'nombreArticles' => $nombreArticles,
        ]);
    }
}