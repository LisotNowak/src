<?php
// src/Controller/FirstController.php
namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class FirstController extends AbstractController
{
    #[Route('/', name: 'app_accueil')]
    public function number(): Response
    {

        return $this->render('accueil.html.twig');
    }

    #[Route('/generateurSignature', name: 'app_generateur_signature')]
    public function generateur_signature(): Response
    {

        return $this->render('generateurSignature.html.twig');
    }
}