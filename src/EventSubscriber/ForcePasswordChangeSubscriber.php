<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForcePasswordChangeSubscriber implements EventSubscriberInterface
{
    private TokenStorageInterface $tokenStorage;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(TokenStorageInterface $tokenStorage, UrlGeneratorInterface $urlGenerator)
    {
        $this->tokenStorage = $tokenStorage;
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Routes to exclude from redirection (adjust names if needed)
        $excludedRoutes = [
            'app_change_password',
            'app_logout',
            'app_login',
            'connect_microsoft_start',
            'connect_microsoft_check',
        ];

        // Don't redirect for excluded routes or when route is null (assets, etc.)
        if ($route === null || in_array($route, $excludedRoutes, true)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token ? $token->getUser() : null;

        if ($user instanceof User && !$user->isPasswordChanged()) {
            $url = $this->urlGenerator->generate('app_change_password');
            $event->setResponse(new RedirectResponse($url));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }
}