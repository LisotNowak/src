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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

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

    public function authenticate(Request $request): SelfValidatingPassport
    {
        /** @var MicrosoftClient $client */
        $client = $this->clientRegistry->getClient('microsoft');
        $accessToken = $this->fetchAccessToken($client);
        $microsoftUser = $client->fetchUserFromToken($accessToken);

        $email = $microsoftUser->getEmail() ?? $microsoftUser->toArray()['userPrincipalName'] ?? null;

        if (!$email) {
            throw new AuthenticationException('Impossible de récupérer l’adresse e-mail Microsoft.');
        }

        return new SelfValidatingPassport(new UserBadge($email, function (string $userIdentifier): ?UserInterface {
            // Chercher l'utilisateur local correspondant
            $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

            if (!$user) {
                // Facultatif : création automatique de l'utilisateur
                $user = new User();
                $user->setEmail($userIdentifier);
                $user->setUsername($userIdentifier);
                $user->setRoles(['ROLE_USER']);

                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?RedirectResponse
    {
        // Redirige vers la page d'accueil après connexion
        return new RedirectResponse('/');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        // Redirige vers /login en cas d’échec
        return new RedirectResponse('/login');
    }
}
