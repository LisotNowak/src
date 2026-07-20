<?php

namespace App\Controller;

use App\Entity\portail\PortalTile;
use App\Form\portail\PortalTileType;
use App\Repository\portail\PortalTileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/portail')]
#[IsGranted('ROLE_ADMIN')]
class PortalAdminController extends AbstractController
{
    #[Route('/', name: 'admin_portail_index')]
    public function index(PortalTileRepository $tileRepository): Response
    {
        return $this->render('admin/portail/index.html.twig', [
            'tiles' => $tileRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_portail_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $tile = new PortalTile();
        $form = $this->createForm(PortalTileType::class, $tile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($tile);
            $em->flush();

            $this->addFlash('success', 'Tuile créée avec succès');
            return $this->redirectToRoute('admin_portail_index');
        }

        return $this->render('admin/portail/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_portail_edit')]
    public function edit(Request $request, PortalTile $tile, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PortalTileType::class, $tile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tile->touch();
            $em->flush();

            $this->addFlash('success', 'Tuile mise à jour');
            return $this->redirectToRoute('admin_portail_index');
        }

        return $this->render('admin/portail/edit.html.twig', [
            'form' => $form->createView(),
            'tile' => $tile,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_portail_delete', methods: ['POST'])]
    public function delete(Request $request, PortalTile $tile, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tile->getId(), $request->request->get('_token'))) {
            $em->remove($tile);
            $em->flush();
            $this->addFlash('success', 'Tuile supprimée');
        }

        return $this->redirectToRoute('admin_portail_index');
    }
}
