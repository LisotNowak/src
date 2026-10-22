<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SecurityController extends AbstractController
{
    use TargetPathTrait;

    #[Route(path: '/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Mémorise la page d'origine pour y revenir après connexion (formulaire ou Microsoft).
        // Seuls les chemins internes sont acceptés pour éviter les redirections ouvertes.
        $targetPath = $request->query->get('_target_path');
        if (is_string($targetPath) && preg_match('#^/(?![/\\\\])#', $targetPath) && !str_starts_with($targetPath, '/login')) {
            $this->saveTargetPath($request->getSession(), 'main', $targetPath);
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            
            if($error != null){
                return $this->render('accueil.html.twig', [
                    'last_username' => $lastUsername,
                    'error' => $error,
                ]);
            }else{
                return $this->render('security/login.html.twig', [
                    'last_username' => $lastUsername,
                    'error' => $error,
                ]);
            }
        }else{
            return $this->render('security/login.html.twig', [
                'last_username' => $lastUsername,
                'error' => $error,
            ]);
        }

            
        
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }


    #[Route('/connect/microsoft', name: 'connect_microsoft_start')]
    public function connectMicrosoftStart(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('microsoft')
            ->redirect([
                'openid',
                'profile', 
                'email',
                'offline_access',
                'User.Read'
            ]);
    }

    #[Route('/connect/microsoft/check', name: 'connect_microsoft_check')]
    public function connectMicrosoftCheck(): Response
    {
        // This method will not be executed - it's handled by the MicrosoftAuthenticator
        throw new \LogicException('This method should never be called. Check your security configuration.');
    }
}