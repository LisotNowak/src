<?php

namespace App\Controller;

use App\Entity\client\AssociationSignataire;
use App\Entity\client\Signataire;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN_CLIENTS')]
class SignataireController extends AbstractController
{
    #[Route('/clients/signataires', name: 'app_signataire_list', methods: ['GET'])]
    public function list(ManagerRegistry $doctrine): Response
    {
        $signataires = $doctrine->getRepository(Signataire::class)->findBy([], ['nom' => 'ASC']);

        // Nombre de clients associés par signataire (affiché avant suppression)
        $rows = $doctrine->getRepository(AssociationSignataire::class)->createQueryBuilder('a')
            ->select('IDENTITY(a.signataire) AS signataireId, COUNT(a.id) AS nb')
            ->groupBy('a.signataire')
            ->getQuery()
            ->getArrayResult();
        $nbAssociations = array_column($rows, 'nb', 'signataireId');

        return $this->render('client/signataires.html.twig', [
            'signataires' => $signataires,
            'nbAssociations' => $nbAssociations,
        ]);
    }

    #[Route('/clients/signataires/new', name: 'app_signataire_new', methods: ['POST'])]
    public function new(Request $request, ManagerRegistry $doctrine): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('signataire_new', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_signataire_list');
        }

        $nom = trim((string) $request->request->get('nom'));
        if ($nom === '') {
            $this->addFlash('error', 'Le nom du signataire est obligatoire.');
            return $this->redirectToRoute('app_signataire_list');
        }

        $repo = $doctrine->getRepository(Signataire::class);
        if ($repo->findOneBy(['nom' => $nom])) {
            $this->addFlash('error', sprintf('Le signataire "%s" existe déjà.', $nom));
            return $this->redirectToRoute('app_signataire_list');
        }

        $signataire = new Signataire();
        $signataire->setNom($nom);
        $em = $doctrine->getManager();
        $em->persist($signataire);
        $em->flush();

        $this->addFlash('success', sprintf('Signataire "%s" ajouté.', $nom));

        return $this->redirectToRoute('app_signataire_list');
    }

    #[Route('/clients/signataires/{id}/edit', name: 'app_signataire_edit', methods: ['POST'])]
    public function edit(Signataire $signataire, Request $request, ManagerRegistry $doctrine): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('signataire_edit_' . $signataire->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_signataire_list');
        }

        $nom = trim((string) $request->request->get('nom'));
        if ($nom === '') {
            $this->addFlash('error', 'Le nom du signataire est obligatoire.');
            return $this->redirectToRoute('app_signataire_list');
        }

        $existing = $doctrine->getRepository(Signataire::class)->findOneBy(['nom' => $nom]);
        if ($existing && $existing->getId() !== $signataire->getId()) {
            $this->addFlash('error', sprintf('Le signataire "%s" existe déjà.', $nom));
            return $this->redirectToRoute('app_signataire_list');
        }

        $signataire->setNom($nom);
        $doctrine->getManager()->flush();

        $this->addFlash('success', sprintf('Signataire renommé en "%s".', $nom));

        return $this->redirectToRoute('app_signataire_list');
    }

    #[Route('/clients/signataires/{id}/delete', name: 'app_signataire_delete', methods: ['POST'])]
    public function delete(Signataire $signataire, Request $request, ManagerRegistry $doctrine): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('signataire_delete_' . $signataire->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_signataire_list');
        }

        $em = $doctrine->getManager();
        $nom = $signataire->getNom();

        // Supprime d'abord les associations clients ↔ signataire
        $nbSupprimees = $em->createQueryBuilder()
            ->delete(AssociationSignataire::class, 'a')
            ->where('a.signataire = :signataire')
            ->setParameter('signataire', $signataire)
            ->getQuery()
            ->execute();

        $em->remove($signataire);
        $em->flush();

        $this->addFlash('success', sprintf('Signataire "%s" supprimé (%d association(s) supprimée(s)).', $nom, $nbSupprimees));

        return $this->redirectToRoute('app_signataire_list');
    }
}
