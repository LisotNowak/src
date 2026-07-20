<?php

namespace App\Controller;

use App\Entity\portail\PortalCategory;
use App\Form\portail\PortalCategoryType;
use App\Repository\portail\PortalCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/portail/categories')]
#[IsGranted('ROLE_ADMIN')]
class PortalCategoryAdminController extends AbstractController
{
    #[Route('/', name: 'admin_portail_categorie_index')]
    public function index(PortalCategoryRepository $categoryRepository): Response
    {
        return $this->render('admin/portail/categories/index.html.twig', [
            'categories' => $categoryRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_portail_categorie_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $category = new PortalCategory();
        $form = $this->createForm(PortalCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Catégorie créée avec succès');
            return $this->redirectToRoute('admin_portail_categorie_index');
        }

        return $this->render('admin/portail/categories/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_portail_categorie_edit')]
    public function edit(Request $request, PortalCategory $category, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PortalCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Catégorie mise à jour');
            return $this->redirectToRoute('admin_portail_categorie_index');
        }

        return $this->render('admin/portail/categories/edit.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_portail_categorie_delete', methods: ['POST'])]
    public function delete(Request $request, PortalCategory $category, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            if (!$category->getTiles()->isEmpty()) {
                $this->addFlash('danger', 'Impossible de supprimer : des applications sont encore rattachées à cette catégorie.');
                return $this->redirectToRoute('admin_portail_categorie_index');
            }

            $em->remove($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée');
        }

        return $this->redirectToRoute('admin_portail_categorie_index');
    }
}
