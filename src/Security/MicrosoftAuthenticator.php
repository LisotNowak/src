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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Psr\Log\LoggerInterface;

class MicrosoftAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private UrlGeneratorInterface $urlGenerator;
    private UserRepository $userRepository;
    private LoggerInterface $logger;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        UserRepository $userRepository,
        LoggerInterface $logger
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        $this->logger->debug('Checking if request is supported', [
            'route' => $request->attributes->get('_route'),
            'uri' => $request->getUri()
        ]);
        
        return $request->attributes->get('_route') === 'connect_microsoft_check';
    }

    public function authenticate(Request $request): Passport
    {
        $this->logger->debug('Starting Microsoft authentication process');
        
        try {
            $client = $this->clientRegistry->getClient('microsoft');
            $accessToken = $this->fetchAccessToken($client);
            
            $this->logger->debug('Access token fetched successfully');
            
            $microsoftUser = $client->fetchUserFromToken($accessToken);
            $email = $microsoftUser->getEmail();
            
            $this->logger->debug('Microsoft user data fetched', ['email' => $email]);

            if (!$email) {
                throw new AuthenticationException('No email found from Microsoft');
            }

            return new SelfValidatingPassport(
                new UserBadge($email, function($userIdentifier) {
                    $this->logger->debug('Looking up user', ['email' => $userIdentifier]);
                    
                    $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
                    
                    if (!$user) {
                        $this->logger->error('User not found in database', ['email' => $userIdentifier]);
                        throw new AuthenticationException('User not found in local database');
                    }
                    
                    $this->logger->debug('User found successfully', ['email' => $userIdentifier]);
                    return $user;
                })
            );
        } catch (\Exception $e) {
            $this->logger->error('Authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        $this->logger->debug('Authentication successful', ['user' => $token->getUserIdentifier()]);
        return new RedirectResponse($this->urlGenerator->generate('app_accueil'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        $this->logger->error('Authentication failed', ['error' => $exception->getMessage()]);
        
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}