<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Ce code ne sera jamais exécuté, géré par Symfony
        throw new \LogicException('Intercepted by the firewall.');
    }

    #[Route('/connect/microsoft', name: 'connect_microsoft_start')]
    public function connectMicrosoft(ClientRegistry $clientRegistry)
    {
        // Redirige l'utilisateur vers Microsoft (la lib KnpU gère les scopes)
        return $clientRegistry
            ->getClient('microsoft')
            ->redirect([
                'openid',
                'profile',
                'email',
                'User.Read',
            ]);
    }

    #[Route('/connect/microsoft/check', name: 'connect_microsoft_check')]
    public function connectMicrosoftCheck(): void
    {
        // ⚠️ NE RIEN METTRE ICI
        // Ce point est intercepté par l’authenticator Microsoft
        // et ne doit pas renvoyer de réponse.
    }
}
