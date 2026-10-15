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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    $associations = [];

    $qb = $clientRepo->createQueryBuilder('c');

    // Si un signataire est sélectionné, récupérer uniquement ses clients associés
    if ($signataireId) {
        $qb->innerJoin('c.associations', 'a')
            ->andWhere('a.signataire = :signataire')
            ->setParameter('signataire', $signataireId);
    }

    // Sociétés disponibles pour le filtre : clients de la catégorie choisie
    // (et associés au signataire sélectionné, s'il y en a un)
    $societeSelected = trim((string) $request->query->get('societe', ''));
    $societes = [];

    if ($categorieId) {
        $qb->andWhere('c.categorieEntity = :categorie')
           ->setParameter('categorie', $categorieId);

        $societeQb = $clientRepo->createQueryBuilder('c')
            ->select('DISTINCT c.societeNom')
            ->andWhere('c.categorieEntity = :categorie')
            ->andWhere("c.societeNom IS NOT NULL AND c.societeNom <> ''")
            ->setParameter('categorie', $categorieId)
            ->orderBy('c.societeNom', 'ASC');
        if ($signataireId) {
            $societeQb->innerJoin('c.associations', 'a')
                ->andWhere('a.signataire = :signataire')
                ->setParameter('signataire', $signataireId);
        }
        $societes = $societeQb->getQuery()->getSingleColumnResult();

        // Ignore une société qui ne fait plus partie des choix (ex. après changement de catégorie/signataire)
        if ($societeSelected !== '' && in_array($societeSelected, $societes, true)) {
            $qb->andWhere('c.societeNom = :societe')
               ->setParameter('societe', $societeSelected);
        } else {
            $societeSelected = '';
        }
    } else {
        $societeSelected = '';
    }

    // Pagination
    // $qb->setFirstResult(($page - 1) * $pageSize)
    //    ->setMaxResults($pageSize);

    // Tri alphabétique par nom puis prénom
    $qb->orderBy('c.triNom', 'ASC')
       ->addOrderBy('c.triPrenom', 'ASC');

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

    // Sociétés distinctes par catégorie (pour le filtre de la modale d'association par catégorie)
    $societesParCategorie = [];
    $rows = $clientRepo->createQueryBuilder('c')
        ->select('DISTINCT IDENTITY(c.categorieEntity) AS categorieId, c.societeNom')
        ->andWhere('c.categorieEntity IS NOT NULL')
        ->andWhere("c.societeNom IS NOT NULL AND c.societeNom <> ''")
        ->orderBy('c.societeNom', 'ASC')
        ->getQuery()
        ->getArrayResult();
    foreach ($rows as $row) {
        $societesParCategorie[$row['categorieId']][] = $row['societeNom'];
    }

        return $this->render('client/list.html.twig', [
            'societes_par_categorie' => $societesParCategorie,
            'clients' => $clients,
            'signataires' => $signataires,
            'signataire_selected' => $signataireId ? (int) $signataireId : null,
            'categories' => $categories,
            'categorie_selected' => $categorieId ? (int) $categorieId : null,
            'societes' => $societes,
            'societe_selected' => $societeSelected,
            'associations' => $associations,
        ]);


}

    #[Route('/client/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ManagerRegistry $doctrine): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        $signataireRepo = $doctrine->getRepository(Signataire::class);
        $signataires = $signataireRepo->findAll();

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $doctrine->getManager();
            $entityManager->persist($client);

            $signataireIds = $request->request->all('signataires');
            foreach ($signataireRepo->findBy(['id' => $signataireIds]) as $signataire) {
                $association = new AssociationSignataire();
                $association->setClient($client);
                $association->setSignataire($signataire);
                $association->setConserver(false);
                $association->setImprimer(false);
                $association->setEnvoiMail(false);
                $entityManager->persist($association);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le client a été ajouté avec succès.');

            // Redirige vers la liste des clients, en sélectionnant le signataire si possible
            return $this->redirectToRoute('app_clients_list');
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form,
            'signataires' => $signataires,
            'selectedSignataires' => [],
        ]);
    }

    #[Route('/client/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Client $client, Request $request, ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $signataireRepo = $entityManager->getRepository(Signataire::class);
        $associationRepo = $entityManager->getRepository(AssociationSignataire::class);

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        $signataires = $signataireRepo->findAll();
        $existingAssociations = $associationRepo->findBy(['client' => $client]);
        $selectedSignataires = array_map(fn($a) => $a->getSignataire()->getId(), $existingAssociations);

        if ($form->isSubmitted() && $form->isValid()) {
            $signataireIds = $request->request->all('signataires');

            // Ajoute les nouvelles associations sélectionnées
            foreach ($signataireRepo->findBy(['id' => $signataireIds]) as $signataire) {
                $existing = $associationRepo->findOneBy(['client' => $client, 'signataire' => $signataire]);
                if (!$existing) {
                    $association = new AssociationSignataire();
                    $association->setClient($client);
                    $association->setSignataire($signataire);
                    $association->setConserver(false);
                    $association->setImprimer(false);
                    $association->setEnvoiMail(false);
                    $entityManager->persist($association);
                }
            }

            // Retire les associations décochées
            foreach ($existingAssociations as $association) {
                if (!in_array((string) $association->getSignataire()->getId(), $signataireIds, true)) {
                    $entityManager->remove($association);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le client a été modifié avec succès.');

            return $this->redirectToRoute('app_clients_list');
        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form,
            'signataires' => $signataires,
            'selectedSignataires' => $selectedSignataires,
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
                        $association->setImprimer(false);
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



    #[Route('/clients/associate/signataire/{id}', name: 'app_client_associate_signataire_clients', methods: ['GET'])]
    public function signataireClients(Signataire $signataire, ManagerRegistry $doctrine): JsonResponse
    {
        $associations = $doctrine->getRepository(AssociationSignataire::class)->createQueryBuilder('a')
            ->innerJoin('a.client', 'c')
            ->addSelect('c')
            ->andWhere('a.signataire = :signataire')
            ->setParameter('signataire', $signataire)
            ->orderBy('c.triNom', 'ASC')
            ->addOrderBy('c.triPrenom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'signataire' => ['id' => $signataire->getId(), 'nom' => $signataire->getNom()],
            'clients' => array_map(fn(AssociationSignataire $a) => [
                'associationId' => $a->getId(),
                'clientId' => $a->getClient()->getId(),
                'nom' => $a->getClient()->getTriNom(),
                'prenom' => $a->getClient()->getTriPrenom(),
                'ville' => $a->getClient()->getVille(),
                'codePostal' => $a->getClient()->getCodePostal(),
                'societe' => $a->getClient()->getSocieteNom(),
            ], $associations),
        ]);
    }

    #[Route('/clients/associations/delete', name: 'app_client_associations_delete', methods: ['POST'])]
    public function deleteAssociations(Request $request, ManagerRegistry $doctrine, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'error' => 'Requête non autorisée'], 400);
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('delete_associations', $csrfToken))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $ids = array_filter(array_map('intval', (array) ($data['ids'] ?? [])));
        if (!$ids) {
            return $this->json(['success' => false, 'error' => 'Aucune association sélectionnée'], 400);
        }

        $entityManager = $doctrine->getManager();
        $associations = $entityManager->getRepository(AssociationSignataire::class)->findBy(['id' => $ids]);
        // Les IDs sont lus avant suppression (Doctrine les vide après le flush)
        $deletedIds = array_map(fn(AssociationSignataire $a) => $a->getId(), $associations);
        foreach ($associations as $association) {
            $entityManager->remove($association);
        }
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'deleted' => count($deletedIds),
            'deletedIds' => $deletedIds,
        ]);
    }

    #[Route('/clients/associate-categorie', name: 'app_client_associate_categorie', methods: ['POST'])]
    public function associateCategorie(Request $request, ManagerRegistry $doctrine, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'error' => 'Requête non autorisée'], 400);
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('associate_categorie', $csrfToken))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['signataire'])) {
            return $this->json(['success' => false, 'error' => 'Veuillez choisir un signataire'], 400);
        }

        $entityManager = $doctrine->getManager();
        $signataire = $entityManager->getRepository(Signataire::class)->find($data['signataire']);
        if (!$signataire) {
            return $this->json(['success' => false, 'error' => 'Signataire introuvable'], 404);
        }

        // Catégorie vide = toutes les catégories (tous les clients)
        $qb = $entityManager->getRepository(Client::class)->createQueryBuilder('c');
        if (!empty($data['categorie'])) {
            $categorie = $entityManager->getRepository(Categorie::class)->find($data['categorie']);
            if (!$categorie) {
                return $this->json(['success' => false, 'error' => 'Catégorie introuvable'], 404);
            }
            $qb->andWhere('c.categorieEntity = :categorie')->setParameter('categorie', $categorie);

            // Filtre optionnel sur la société (uniquement quand une catégorie est choisie)
            if (!empty($data['societe'])) {
                $qb->andWhere('c.societeNom = :societe')->setParameter('societe', (string) $data['societe']);
            }
        }
        $clients = $qb->getQuery()->getResult();

        // IDs des clients déjà associés à ce signataire (une seule requête pour éviter les doublons)
        $existingIds = $entityManager->getRepository(AssociationSignataire::class)->createQueryBuilder('a')
            ->select('IDENTITY(a.client) AS clientId')
            ->andWhere('a.signataire = :signataire')
            ->setParameter('signataire', $signataire)
            ->getQuery()
            ->getSingleColumnResult();
        $existingIds = array_flip(array_map('intval', $existingIds));

        $added = 0;
        $alreadyPresent = 0;
        foreach ($clients as $client) {
            if (isset($existingIds[$client->getId()])) {
                $alreadyPresent++;
                continue;
            }

            $association = new AssociationSignataire();
            $association->setClient($client);
            $association->setSignataire($signataire);
            $association->setConserver(true);
            $association->setImprimer(false);
            $association->setEnvoiMail(false);
            $entityManager->persist($association);

            $existingIds[$client->getId()] = true;
            $added++;
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'added' => $added,
            'alreadyPresent' => $alreadyPresent,
            'total' => count($clients),
        ]);
    }

    #[Route('/clients/update-field', name: 'app_clients_update_field', methods: ['POST'])]
    public function updateField(Request $request, ManagerRegistry $doctrine, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'error' => 'Requête non autorisée'], 400);
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('update_client_field', $csrfToken))) {
            return $this->json(['success' => false, 'error' => 'Token CSRF invalide'], 403);
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

        if (!in_array($data['field'], ['imprimer', 'conserver', 'envoiMail'])) {
            return $this->json(['success' => false, 'error' => 'Champ non autorisé'], 400);
        }

        $boolValue = $data['value'] == '1';

        match($data['field']) {
            'imprimer' => $association->setImprimer($boolValue),
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
        Client $client,
        CsrfTokenManagerInterface $csrfTokenManager
    ): JsonResponse {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false], 400);
        }

        $csrfToken = $request->headers->get('X-CSRF-TOKEN');
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('update_client_address', $csrfToken))) {
            return new JsonResponse(['success' => false, 'error' => 'Token CSRF invalide'], 403);
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
    #[IsGranted('ROLE_ADMIN')]
    public function deleteList(ManagerRegistry $doctrine): Response
    {
        $clients = $doctrine->getRepository(Client::class)->findAll();

        return $this->render('client/delete.html.twig', [
            'clients' => $clients,
        ]);
    }


    #[Route('/client/{id}/delete', name: 'app_client_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
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
