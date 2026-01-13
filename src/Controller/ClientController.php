<?php

namespace App\Controller;

use App\Entity\client\Client;
use App\Entity\client\AssociationSignataire;
use App\Entity\client\Signataire;
use App\Entity\client\Categorie;
use App\Form\ClientType; // <-- Ajoutez cette ligne
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    $associations = [];

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

        // Charger les associations uniquement pour les clients récupérés
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
    }

        return $this->render('client/list.html.twig', [
            'clients' => $clients,
            'signataires' => $signataires,
            'signataire_selected' => $signataireId ? (int) $signataireId : null,
            'categories' => $categories,
            'categorie_selected' => $categorieId ? (int) $categorieId : null,
            'associations' => $associations,
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

    #[Route('/clients/associate', name: 'app_client_associate', methods: ['GET', 'POST'])]
    public function associate(Request $request, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $signataireRepo = $entityManager->getRepository(Signataire::class);
        $clientRepo = $entityManager->getRepository(Client::class);

        if ($request->isMethod('POST')) {
            // ✅ Récupération de plusieurs signataires et clients
            $signataireIds = $request->request->all('signataires');
            $clientIds = $request->request->all('clients');

            if (empty($signataireIds) || empty($clientIds)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins un signataire et un client.');
                return $this->redirectToRoute('app_client_associate');
            }

            // Récupérer les entités
            $signataires = $signataireRepo->findBy(['id' => $signataireIds]);
            $clientsToAssociate = $clientRepo->findBy(['id' => $clientIds]);

            // ✅ Boucles imbriquées : chaque client ↔ chaque signataire
            foreach ($signataires as $signataire) {
                foreach ($clientsToAssociate as $client) {
                    $existingAssociation = $entityManager->getRepository(AssociationSignataire::class)->findOneBy([
                        'client' => $client,
                        'signataire' => $signataire
                    ]);

                    if (!$existingAssociation) {
                        $association = new AssociationSignataire();
                        $association->setClient($client);
                        $association->setSignataire($signataire);
                        $association->setConserver(false);
                        $association->setSignature(false);
                        $association->setEnvoiMail(false);
                        $entityManager->persist($association);
                    }
                }
            }

            $entityManager->flush();

            // ✅ Message de confirmation
            $this->addFlash(
                'success',
                sprintf(
                    '%d client(s) ont été associés à %d signataire(s) avec succès.',
                    count($clientsToAssociate),
                    count($signataires)
                )
            );

            return $this->redirectToRoute('app_client_associate');
        }

        // Pour le GET : charger tous les signataires et clients
        $clients = $clientRepo->findBy([], ['triNom' => 'ASC', 'triPrenom' => 'ASC']);

        return $this->render('client/associate.html.twig', [
            'signataires' => $signataireRepo->findAll(),
            'clients' => $clients,
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
    public function updateAddress(
        Request $request,
        ManagerRegistry $doctrine,
        Client $client
    ): JsonResponse {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false], 400);
        }

        $data = json_decode($request->getContent(), true);
        $em = $doctrine->getManager();

        $client->setTriNom($data['nom'] ?? '');
        $client->setTriPrenom($data['prenom'] ?? '');
        $client->setAdresse1($data['adresse1'] ?? '');
        $client->setCodePostal($data['codePostal'] ?? '');
        $client->setVille($data['ville'] ?? '');
        $client->setPays($data['pays'] ?? '');
        $client->setSocieteNom($data['societe'] ?? '');

        if (!empty($data['categorie'])) {
            $categorie = $em->getRepository(Categorie::class)->find($data['categorie']);
            $client->setCategorieEntity($categorie);
        } else {
            $client->setCategorieEntity(null);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }



    #[Route('/clients/delete', name: 'app_client_delete_list')]
    public function deleteList(ManagerRegistry $doctrine): Response
    {
        $clients = $doctrine->getRepository(Client::class)->findAll();

        return $this->render('client/delete.html.twig', [
            'clients' => $clients,
        ]);
    }


    #[Route('/client/{id}/delete', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Client $client, ManagerRegistry $doctrine, Request $request): RedirectResponse
    {
        $submittedToken = $request->request->get('_token');

        if ($this->isCsrfTokenValid('delete_client_' . $client->getId(), $submittedToken)) {
            $em = $doctrine->getManager();
            foreach ($client->getAssociations() as $assoc) {
                $em->remove($assoc);
            }
            $em->remove($client);
            $em->flush();
        }

        return $this->redirectToRoute('app_client_delete_list');
    }

}
