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
        return $request->attributes->get('_route') === 'connect_microsoft_check';
    }

    public function authenticate(Request $request): Passport
    {
        try {
            if (!$this->supports($request)) {
                throw new AuthenticationException('Méthode d\'authentification non prise en charge');
            }

            /** @var MicrosoftClient $client */
            $client = $this->clientRegistry->getClient('microsoft');
            
            try {
                $accessToken = $this->fetchAccessToken($client);
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                throw new AuthenticationException('Échec de l\'obtention du jeton d\'accès : ' . $e->getMessage());
            }

            try {
                $microsoftUser = $client->fetchUserFromToken($accessToken);
                $email = $microsoftUser->getEmail();
            } catch (\Exception $e) {
                throw new AuthenticationException('Échec de la récupération des détails de l\'utilisateur : ' . $e->getMessage());
            }

            if (!$email) {
                throw new AuthenticationException('Email non fourni par Microsoft');
            }

            return new SelfValidatingPassport(
                new UserBadge($email, function ($userIdentifier) use ($microsoftUser) {
                    $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                    if (!$user) {
                        throw new AuthenticationException(sprintf(
                            'Utilisateur avec l\'email "%s" non trouvé dans la base de données locale',
                            $userIdentifier
                        ));
                    }

                    try {
                        if ($microsoftUser->getName() && $user->getUsername() !== $microsoftUser->getName()) {
                            $user->setUsername($microsoftUser->getName());
                            $this->entityManager->persist($user);
                            $this->entityManager->flush();
                        }
                    } catch (\Exception $e) {
                        // Enregistrer l'erreur mais ne pas échouer l'authentification
                        error_log('Échec de la mise à jour du nom d\'utilisateur : ' . $e->getMessage());
                    }

                    return $user;
                })
            );
        } catch (\Exception $e) {
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
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('success', 'Connecté avec succès via Microsoft');
        }
        
        return new RedirectResponse(
            $this->urlGenerator->generate('app_accueil')
        );
    }
}