<?php

namespace App\Controller;

use App\Entity\client\LiaisonSignataires;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
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
        CategorieRepository $categorieRepository,
        ManagerRegistry $doctrine
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

        // Charger les liaisons pour chaque client (par uniqueid)
        $liaisonRepo = $doctrine->getRepository(LiaisonSignataires::class);
        $liaisons = [];
        foreach ($clients as $client) {
            $liaison = $liaisonRepo->findOneBy(['uniqueid' => $client->getUniqueId()]);
            $liaisons[$client->getUniqueId()] = $liaison;
        }

        return $this->render('client/list.html.twig', [
            'clients' => $clients,
            'liaisons' => $liaisons,
            'signataires' => $signataires,
            'categories' => $categories,
            'signataire_selected' => $signataireId,
            'categorie_selected' => $categorieId,
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
        $liaisonRepo = $doctrine->getRepository(LiaisonSignataires::class);
        $liaison = $liaisonRepo->findOneBy([
            'uniqueid' => $data['id'],
            'signataire' => $data['signataire'],
        ]);
        if (!$liaison) {
            return $this->json(['success' => false, 'error' => 'Liaison introuvable'], 404);
        }
        if (!in_array($data['field'], ['signature', 'conserver', 'envoiMail'])) {
            return $this->json(['success' => false, 'error' => 'Champ non autorisé'], 400);
        }
        $boolValue = $data['value'] == '1' ? true : false;
        if ($data['field'] === 'signature') {
            $liaison->setSignature($boolValue);
        } elseif ($data['field'] === 'conserver') {
            $liaison->setConserver($boolValue);
        } elseif ($data['field'] === 'envoiMail') {
            $liaison->setEnvoiMail($boolValue);
        }
        $em = $doctrine->getManager();
        $em->persist($liaison);
        $em->flush();
        return $this->json(['success' => true]);
    }
}
