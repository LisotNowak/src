<?php

namespace App\Controller;

use App\Repository\client\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClientController extends AbstractController
{
    #[Route('/clients', name: 'app_clients_list')]
    public function list(ClientRepository $clientRepository): Response
    {
        $clients = $clientRepository->findAll();

        return $this->render('client/list.html.twig', [
            'clients' => $clients,
        ]);
    }
}
