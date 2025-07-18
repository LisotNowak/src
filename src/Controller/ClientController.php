<?php

namespace App\Controller;

use App\Repository\client\ClientRepository;
use App\Repository\client\SignataireRepository;
use App\Repository\client\CategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClientController extends AbstractController
{
    #[Route('/clients', name: 'app_clients_list')]
    public function list(
        Request $request,
        ClientRepository $clientRepository,
        SignataireRepository $signataireRepository,
        CategorieRepository $categorieRepository
    ): Response
    {
        $signataireId = $request->query->get('signataire');
        $categorieId = $request->query->get('categorie');

        $signataires = $signataireRepository->findAll();
        $categories = $categorieRepository->findAll();

        $qb = $clientRepository->createQueryBuilder('c');

        if ($signataireId) {
            $signataire = $signataireRepository->find($signataireId);
            $qb->andWhere('c.signataires LIKE :signataire')
            ->setParameter('signataire', '%' . $signataire->getSignataire() . '%');
        }

        if ($categorieId) {
            $qb->andWhere('c.categorie = :categorie')
            ->setParameter('categorie', $categorieId);
        }

        $clients = ($signataireId || $categorieId)
            ? $qb->getQuery()->getResult()
            : [];

        return $this->render('client/list.html.twig', [
            'clients' => $clients,
            'signataires' => $signataires,
            'categories' => $categories,
            'signataire_selected' => $signataireId,
            'categorie_selected' => $categorieId,
        ]);
    }
}
