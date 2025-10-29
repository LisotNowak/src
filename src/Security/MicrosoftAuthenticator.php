<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\Provider\MicrosoftClient;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class MicrosoftAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): ?bool
    {
        $supports = $request->attributes->get('_route') === 'connect_microsoft_check';
        
        // Add debug logging
        if ($supports) {
            error_log('MicrosoftAuthenticator: Supporting authentication request');
        } else {
            error_log('MicrosoftAuthenticator: Not supporting request for route: ' . $request->attributes->get('_route'));
        }
        
        return $supports;
    }

    public function authenticate(Request $request): Passport
    {
        try {
            if (!$this->supports($request)) {
                error_log('MicrosoftAuthenticator: Authentication attempted on unsupported route');
                throw new AuthenticationException('Méthode d\'authentification non prise en charge');
            }

            /** @var MicrosoftClient $client */
            $client = $this->clientRegistry->getClient('microsoft');
            
            try {
                error_log('MicrosoftAuthenticator: Attempting to fetch access token');
                $accessToken = $this->fetchAccessToken($client);
                error_log('MicrosoftAuthenticator: Access token obtained successfully');
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                error_log('MicrosoftAuthenticator: Failed to get access token - ' . $e->getMessage());
                throw new AuthenticationException('Échec de l\'obtention du jeton d\'accès : ' . $e->getMessage());
            }

            try {
                error_log('MicrosoftAuthenticator: Fetching user from token');
                $microsoftUser = $client->fetchUserFromToken($accessToken);
                $email = $microsoftUser->getEmail();
                error_log('MicrosoftAuthenticator: Retrieved email: ' . $email);
            } catch (\Exception $e) {
                error_log('MicrosoftAuthenticator: Failed to fetch user details - ' . $e->getMessage());
                throw new AuthenticationException('Échec de la récupération des détails de l\'utilisateur : ' . $e->getMessage());
            }

            if (!$email) {
                error_log('MicrosoftAuthenticator: No email provided by Microsoft');
                throw new AuthenticationException('Email non fourni par Microsoft');
            }

            return new SelfValidatingPassport(
                new UserBadge($email, function ($userIdentifier) use ($microsoftUser) {
                    error_log('MicrosoftAuthenticator: Looking up user with email: ' . $userIdentifier);
                    $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                    if (!$user) {
                        error_log('MicrosoftAuthenticator: User not found in local database');
                        throw new AuthenticationException(sprintf(
                            'Utilisateur avec l\'email "%s" non trouvé dans la base de données locale',
                            $userIdentifier
                        ));
                    }

                    error_log('MicrosoftAuthenticator: User found in local database');
                    return $user;
                })
            );
        } catch (\Exception $e) {
            error_log('MicrosoftAuthenticator: Authentication failed - ' . $e->getMessage());
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }
        
        return new RedirectResponse(
            $this->urlGenerator->generate('app_login')
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        error_log('MicrosoftAuthenticator: Authentication successful');
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('success', 'Connecté avec succès via Microsoft');
        }
        
        return new RedirectResponse(
            $this->urlGenerator->generate('app_accueil')
        );
    }
}