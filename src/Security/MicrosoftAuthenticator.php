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
        $this->logger->debug('MicrosoftAuthenticator::supports', [
            'route' => $request->attributes->get('_route'),
            'uri' => $request->getUri(),
            'method' => $request->getMethod(),
            'query' => $request->query->all()
        ]);
        
        // Check for both the route and the presence of OAuth state/code parameters
        $isSupported = $request->attributes->get('_route') === 'connect_microsoft_check' 
            && ($request->query->has('code') || $request->query->has('state'));
            
        $this->logger->debug('Request support status: ' . ($isSupported ? 'true' : 'false'));
        
        return $isSupported;
    }

    public function authenticate(Request $request): Passport
    {
        $this->logger->debug('Starting Microsoft authentication', [
            'query_parameters' => $request->query->all(),
            'request_uri' => $request->getUri()
        ]);

        try {
            if (!$request->query->has('code')) {
                throw new AuthenticationException('No authorization code present in the request');
            }

            /** @var MicrosoftClient $client */
            $client = $this->clientRegistry->getClient('microsoft');
            
            try {
                $accessToken = $this->fetchAccessToken($client, [
                    'code' => $request->query->get('code'),
                    'state' => $request->query->get('state')
                ]);
                
                $this->logger->debug('Access token obtained successfully');
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                $this->logger->error('Failed to fetch access token', [
                    'error' => $e->getMessage(),
                    'response' => $e->getResponseBody()
                ]);
                throw new AuthenticationException('Failed to fetch access token: ' . $e->getMessage());
            }

            try {
                $microsoftUser = $client->fetchUserFromToken($accessToken);
                $email = $microsoftUser->getEmail();
                
                $this->logger->debug('Microsoft user fetched', [
                    'email' => $email,
                    'name' => $microsoftUser->getName()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to fetch user details', [
                    'error' => $e->getMessage()
                ]);
                throw new AuthenticationException('Failed to fetch user details from Microsoft');
            }

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
            $this->logger->error('Authentication process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new AuthenticationException($e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        $this->logger->debug('Authentication successful', ['user' => $token->getUserIdentifier()]);
        return new RedirectResponse($this->urlGenerator->generate('app_accueil'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        $this->logger->error('Authentication failed', [
            'error' => $exception->getMessage(),
            'request_uri' => $request->getUri(),
            'query_parameters' => $request->query->all()
        ]);
        
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('error', 
                'Erreur d\'authentification Microsoft: ' . $exception->getMessage()
            );
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}