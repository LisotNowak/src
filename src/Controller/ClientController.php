<?php

namespace App\Controller;

use App\Entity\client\Client;
use App\Entity\client\AssociationSignataire;
use App\Entity\client\Signataire;
use App\Entity\client\Categorie;
use App\Form\ClientType; // <-- Ajoutez cette ligne
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse; // <-- Ajoutez cette ligne
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

    return $this->render('client/list.html.twig', [
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

    #[Route('/client/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ManagerRegistry $doctrine): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->persist($client);
            $entityManager->flush();

            $this->addFlash('success', 'Le client a été ajouté avec succès.');

            // Redirige vers la liste des clients, en sélectionnant le signataire si possible
            return $this->redirectToRoute('app_clients_list');
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form,
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

    #[Route('/client/{id}/update-address', name: 'app_client_update_address', methods: ['POST'])]
    public function updateAddress(Request $request, ManagerRegistry $doctrine, Client $client): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'error' => 'Requête non autorisée'], 400);
        }

        $data = json_decode($request->getContent(), true);

        // Mettre à jour les champs de l'adresse
        if (isset($data['adresse1'])) {
            $client->setAdresse1($data['adresse1']);
        }
        if (isset($data['adresse2'])) {
            $client->setAdresse2($data['adresse2']);
        }
        if (isset($data['codePostal'])) {
            $client->setCodePostal($data['codePostal']);
        }
        if (isset($data['ville'])) {
            $client->setVille($data['ville']);
        }
        if (isset($data['pays'])) {
            $client->setPays($data['pays']);
        }

        $em = $doctrine->getManager();
        $em->persist($client);
        $em->flush();

        // Retourner les données mises à jour pour rafraîchir la vue
        $responseData = [
            'success' => true,
            'client' => [
                'id' => $client->getId(),
                'adresse1' => $client->getAdresse1(),
                'adresse2' => $client->getAdresse2(),
                'codePostal' => $client->getCodePostal(),
                'ville' => $client->getVille(),
                'pays' => $client->getPays(),
            ]
        ];

        return new JsonResponse($responseData);
    }
}
