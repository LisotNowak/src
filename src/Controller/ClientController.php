<?php

namespace App\Controller;

use App\Entity\client\Client;
use App\Entity\client\AssociationSignataire;
use App\Entity\client\Signataire;
use App\Entity\client\Categorie;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClientController extends AbstractController
{
#[Route('/clients', name: 'app_clients_list')]
public function list(Request $request, ManagerRegistry $doctrine): Response
{
    $signataireId = $request->query->get('signataire');
    $categorieId = $request->query->get('categorie');
    $page = max(1, (int)$request->query->get('page', 1));
    // $pageSize = 50;

    $clientRepo = $doctrine->getRepository(Client::class);
    $signataireRepo = $doctrine->getRepository(Signataire::class);
    $categorieRepo = $doctrine->getRepository(Categorie::class);
    $associationRepo = $doctrine->getRepository(AssociationSignataire::class);

    $signataires = $signataireRepo->findAll();
    $categories = $categorieRepo->findAll();

    $clients = [];

    // Si un signataire est sélectionné, récupérer uniquement ses clients associés
    if ($signataireId) {
        $qb = $clientRepo->createQueryBuilder('c')
            ->innerJoin('c.associations', 'a')
            ->andWhere('a.signataire = :signataire')
            ->setParameter('signataire', $signataireId);

        if ($categorieId) {
            $qb->andWhere('c.categorieEntity = :categorie')
               ->setParameter('categorie', $categorieId);
        }

        // Pagination
        // $qb->setFirstResult(($page - 1) * $pageSize)
        //    ->setMaxResults($pageSize);

        $clients = $qb->getQuery()->getResult();
    }

    // Charger les associations uniquement pour les clients récupérés
    $associations = [];
    if ($clients) {
        $clientIds = array_map(fn($c) => $c->getId(), $clients);
        $assocList = $associationRepo->createQueryBuilder('a')
            ->andWhere('a.client IN (:clients)')
            ->setParameter('clients', $clientIds)
            ->getQuery()
            ->getResult();

        foreach ($assocList as $assoc) {
            $associations[$assoc->getClient()->getId()][] = $assoc;
        }
    }

    return $this->render('client/temp.html.twig', [
        'clients' => $clients,
        'associations' => $associations,
        'signataires' => $signataires,
        'categories' => $categories,
        'signataire_selected' => $signataireId,
        'categorie_selected' => $categorieId,
        'page' => $page,
        // 'pageSize' => $pageSize,
    ]);
}


    #[Route('/clients/update-field', name: 'app_clients_update_field', methods: ['POST'])]
    public function updateField(Request $request, ManagerRegistry $doctrine): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'error' => 'Requête non autorisée'], 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['id'], $data['signataire'], $data['field'], $data['value'])) {
            return $this->json(['success' => false, 'error' => 'Paramètres manquants'], 400);
        }

        $clientRepo = $doctrine->getRepository(Client::class);
        $signataireRepo = $doctrine->getRepository(Signataire::class);
        $associationRepo = $doctrine->getRepository(AssociationSignataire::class);

        $client = $clientRepo->find($data['id']);
        $signataire = $signataireRepo->find($data['signataire']);

        if (!$client || !$signataire) {
            return $this->json(['success' => false, 'error' => 'Client ou signataire introuvable'], 404);
        }

        $association = $associationRepo->findOneBy([
            'client' => $client,
            'signataire' => $signataire,
        ]);

        if (!$association) {
            return $this->json(['success' => false, 'error' => 'Association introuvable'], 404);
        }

        if (!in_array($data['field'], ['signature', 'conserver', 'envoiMail'])) {
            return $this->json(['success' => false, 'error' => 'Champ non autorisé'], 400);
        }

        $boolValue = $data['value'] == '1';

        match($data['field']) {
            'signature' => $association->setSignature($boolValue),
            'conserver' => $association->setConserver($boolValue),
            'envoiMail' => $association->setEnvoiMail($boolValue),
        };

        $em = $doctrine->getManager();
        $em->persist($association);
        $em->flush();

        return $this->json(['success' => true]);
    }
}
