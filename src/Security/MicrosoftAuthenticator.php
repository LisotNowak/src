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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class MicrosoftAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_microsoft_check';
    }

    public function authenticate(Request $request): Passport
    {
        /** @var MicrosoftClient $client */
        $client = $this->clientRegistry->getClient('microsoft');

        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function ($token) use ($client) {
            $microsoftUser = $client->fetchUserFromToken($token);
            $email = $microsoftUser->getEmail();

            // Cherche l'utilisateur local par email
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                // Optionnel : créer un nouvel utilisateur
                $user = new User();
                $user->setEmail($email);
                $user->setUsername($microsoftUser->getName() ?? $email);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?RedirectResponse
    {
        // Redirection après connexion réussie
        return new RedirectResponse('/'); // ou app_home
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        // Redirection en cas d’échec
        return new RedirectResponse('/login');
    }
}
