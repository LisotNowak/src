<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\log\ConnexionLogRepository;
use App\Repository\log\PageVisiteRepository;
use App\Security\MicrosoftAuthenticator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Alimente les logs d'activité (page Admin > Logs) :
 * - connexions / échecs / déconnexions
 * - pages vues et actions (POST, exports) des utilisateurs connectés
 * Une erreur d'écriture des logs ne doit jamais bloquer l'application.
 */
class ActivityLogSubscriber implements EventSubscriberInterface
{
    /** Attribut de requête lu par base.html.twig pour envoyer le temps passé */
    public const ATTR_VISITE = '_log_visite_id';

    private const ROUTES_IGNOREES = ['app_log_temps'];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ConnexionLogRepository $connexionLogs,
        private PageVisiteRepository $pageVisites,
        private LoggerInterface $logger,
    ) {
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::ATTR_VISITE, $this->uuid4());
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if ($route === null || str_starts_with($route, '_') || in_array($route, self::ROUTES_IGNOREES, true)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        // GET : on ne garde que les vraies pages HTML et les téléchargements (exports, impressions),
        // pas les appels AJAX/JSON qui pollueraient les statistiques
        $response = $event->getResponse();
        if ($request->isMethod('GET')) {
            $estPage = str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')
                && !$request->isXmlHttpRequest();
            $estTelechargement = str_contains((string) $response->headers->get('Content-Disposition'), 'attachment');
            if (!$estPage && !$estTelechargement) {
                return;
            }
        }

        $debut = $request->server->get('REQUEST_TIME_FLOAT');

        $this->ecrire(fn () => $this->pageVisites->enregistrer([
            'id' => $request->attributes->get(self::ATTR_VISITE),
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'session' => $this->empreinteSession($request),
            'methode_http' => $request->getMethod(),
            'route' => $route,
            'chemin' => mb_substr($request->getRequestUri(), 0, 500),
            'statut' => $response->getStatusCode(),
            'duree_serveur_ms' => $debut ? (int) round((microtime(true) - $debut) * 1000) : null,
            'ip' => $request->getClientIp(),
        ]));
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        $this->ecrire(fn () => $this->connexionLogs->enregistrer([
            'evenement' => 'connexion',
            'methode' => $this->methode($event->getAuthenticator()),
            'email' => $user instanceof User ? $user->getEmail() : $user->getUserIdentifier(),
            'user_id' => $user instanceof User ? $user->getId() : null,
            'ip' => $request->getClientIp(),
            'user_agent' => mb_substr((string) $request->headers->get('User-Agent'), 0, 255),
            'session' => $this->empreinteSession($request),
        ]));
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $badge = $event->getPassport()?->getBadge(UserBadge::class);
        $email = $badge instanceof UserBadge ? $badge->getUserIdentifier() : $request->request->get('_username');

        $this->ecrire(fn () => $this->connexionLogs->enregistrer([
            'evenement' => 'echec',
            'methode' => $this->methode($event->getAuthenticator()),
            'email' => $email ? mb_substr((string) $email, 0, 180) : null,
            'ip' => $request->getClientIp(),
            'user_agent' => mb_substr((string) $request->headers->get('User-Agent'), 0, 255),
            'motif' => mb_substr($event->getException()->getMessageKey(), 0, 255),
        ]));
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }
        $request = $event->getRequest();

        $this->ecrire(fn () => $this->connexionLogs->enregistrer([
            'evenement' => 'deconnexion',
            'email' => $user->getEmail(),
            'user_id' => $user->getId(),
            'ip' => $request->getClientIp(),
            'user_agent' => mb_substr((string) $request->headers->get('User-Agent'), 0, 255),
            'session' => $this->empreinteSession($request),
        ]));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 0],
            // Priorité basse : le statut HTTP final est connu
            KernelEvents::RESPONSE => ['onResponse', -1024],
            // Après la régénération de l'identifiant de session
            LoginSuccessEvent::class => ['onLoginSuccess', -100],
            LoginFailureEvent::class => 'onLoginFailure',
            // Avant l'invalidation de la session
            LogoutEvent::class => ['onLogout', 100],
        ];
    }

    private function ecrire(callable $ecriture): void
    {
        try {
            $ecriture();
        } catch (\Throwable $e) {
            $this->logger->warning('Échec d\'écriture du log d\'activité : '.$e->getMessage());
        }
    }

    private function methode(object $authenticator): string
    {
        return match (true) {
            $authenticator instanceof FormLoginAuthenticator => 'formulaire',
            $authenticator instanceof MicrosoftAuthenticator => 'microsoft',
            $authenticator instanceof RememberMeAuthenticator => 'remember_me',
            default => (new \ReflectionClass($authenticator))->getShortName(),
        };
    }

    /** Permet de regrouper les pages par session sans stocker l'identifiant de session réel */
    private function empreinteSession(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }
        $id = $request->getSession()->getId();

        return $id !== '' ? substr(hash('sha256', $id), 0, 16) : null;
    }

    private function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
