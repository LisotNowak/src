<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Psr\Log\LoggerInterface;

class MicrosoftAuthenticator extends OAuth2Authenticator
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $em;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->clientRegistry = $clientRegistry;
        $this->em = $em;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_microsoft_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('microsoft');
        $accessToken = $this->fetchAccessToken($client);

        /** @var \TheNetworg\OAuth2\Client\Provider\AzureResourceOwner $microsoftUser */
        $microsoftUser = $client->fetchUserFromToken($accessToken);
        $data = $microsoftUser->toArray();

        // Essayer plusieurs champs possibles
        $email = $data['mail']
            ?? $data['userPrincipalName']
            ?? ($data['otherMails'][0] ?? null);

        if (!$email) {
            $this->logger->error('Impossible de récupérer l\'email Microsoft', [
                'data' => $data
            ]);
            throw new \RuntimeException('Impossible de récupérer l\'email de l\'utilisateur Microsoft. Consultez var/log/dev.log pour le détail.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function ($userIdentifier) use ($email) {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setRoles(['ROLE_USER']);
                    $this->em->persist($user);
                    $this->em->flush();
                }
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new Response('<script>window.location="/";</script>');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response('Authentication failed: '.$exception->getMessage(), Response::HTTP_FORBIDDEN);
    }
}
