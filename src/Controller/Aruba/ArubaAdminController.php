<?php

namespace App\Controller\Aruba;

use App\Form\Aruba\SwitchSettingsType;
use App\Form\Aruba\SwitchType;
use App\Service\Aruba\Collector;
use App\Service\Aruba\Config;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion switch > Configuration : ajout, modification, suppression des switchs et paramètres communs.
 * Tout est enregistré dans config/aruba/config.json, les mots de passe chiffrés dans config/aruba/secrets.
 * Les changements sont pris en compte à la prochaine actualisation.
 */
#[Route('/gestion-switch/admin', name: 'app_gestion_switch_admin_')]
#[IsGranted('ROLE_GESTION_SWITCH')]
class ArubaAdminController extends AbstractController
{
    private const ID = '[a-z0-9][a-z0-9_.-]*';

    public function __construct(
        private readonly Config $config,
        private readonly Collector $collector,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // état de chaque switch lors de la dernière collecte
        $last = [];
        $snap = $this->collector->snapshot() ?? [];
        foreach ($snap['switches'] ?? [] as $s) {
            $last[$s['id']] = $s;
        }
        foreach ($snap['wifi'] ?? [] as $w) {
            $last[$w['id']] = $w + ['platform' => $w['error'] ? '' : sprintf('%d bornes · %d clients',
                count($w['aps']), count($w['clients']))];
        }
        $all = array_map(fn(array $sw) => $sw + [
            'own' => $this->config->rawSwitch($sw['id']) ?? [],
            'own_password' => $this->config->hasOwnPassword($sw['id']),
            'last' => $last[$sw['id']] ?? null,
        ], $this->config->switches());
        $isWifi = fn(array $sw) => ($sw['os'] ?? '') === 'instant';

        return $this->render('aruba/admin/index.html.twig', [
            'switches' => array_values(array_filter($all, fn($sw) => !$isWifi($sw))),
            'clusters' => array_values(array_filter($all, $isWifi)),
            'defaults' => $this->config->get('defaults', []),
            'common_password' => $this->config->hasOwnPassword('default'),
            'parallel' => $this->config->get('parallel', 8),
            'other_switches' => $this->config->get('other_switches', []),
            'running' => $this->collector->isRunning(),
        ]);
    }

    /** ?type=wifi : cluster de bornes Wi-Fi (Aruba Instant) */
    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $kind = $request->query->get('type') === 'wifi' ? 'wifi' : 'switch';
        $form = $this->createForm(SwitchType::class,
            ['os' => 'aos-cx', 'protocol' => 'https', 'password_mode' => 'common', 'verify_ssl' => 'default'],
            ['defaults' => $this->config->get('defaults', []), 'kind' => $kind]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($this->config->rawSwitch($data['id']) !== null) {
                $form->get('id')->addError(new FormError('Cet identifiant est déjà utilisé.'));
            } elseif ($this->saveSwitch($form, $data, $kind, false)) {
                $this->addFlash('success', sprintf('%s « %s » ajouté : il sera interrogé à la prochaine actualisation.',
                    $kind === 'wifi' ? 'Cluster Wi-Fi' : 'Switch', $data['name'] ?: $data['id']));
                return $this->redirectToRoute('app_gestion_switch_admin_index', ['_fragment' => 'sw-' . $data['id']]);
            }
        }

        return $this->render('aruba/admin/form.html.twig', [
            'form' => $form->createView(),
            'switch' => null,
            'kind' => $kind,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => self::ID], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $raw = $this->config->rawSwitch($id) ?? throw $this->createNotFoundException('Switch inconnu');
        $hasOwn = $this->config->hasOwnPassword($id);
        $kind = ($raw['os'] ?? '') === 'instant' ? 'wifi' : 'switch';
        $form = $this->createForm(SwitchType::class, [
            'id' => $id,
            'name' => $raw['name'] ?? '',
            'host' => $raw['host'] ?? '',
            'site' => $raw['site'] ?? '',
            'os' => $raw['os'] ?? 'aos-cx',
            'protocol' => $raw['protocol'] ?? 'https',
            'username' => $raw['username'] ?? '',
            'password_mode' => $hasOwn ? 'own' : 'common',
            'timeout' => $raw['timeout'] ?? null,
            'api_version' => $raw['api_version'] ?? '',
            'verify_ssl' => !isset($raw['verify_ssl']) ? 'default' : ($raw['verify_ssl'] ? 'yes' : 'no'),
        ], ['is_edit' => true, 'has_own_password' => $hasOwn, 'defaults' => $this->config->get('defaults', []), 'kind' => $kind]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // le champ id est désactivé : il n'est pas renvoyé par le navigateur
            if ($this->saveSwitch($form, ['id' => $id] + $form->getData(), $kind, $hasOwn, $raw)) {
                $this->addFlash('success', sprintf('%s « %s » enregistré.', $kind === 'wifi' ? 'Cluster Wi-Fi' : 'Switch',
                    $form->getData()['name'] ?: $id));
                return $this->redirectToRoute('app_gestion_switch_admin_index', ['_fragment' => 'sw-' . $id]);
            }
        }

        return $this->render('aruba/admin/form.html.twig', [
            'form' => $form->createView(),
            'switch' => ['id' => $id] + $raw,
            'kind' => $kind,
            'effective' => $this->effective($raw, $hasOwn),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => self::ID], methods: ['POST'])]
    public function delete(Request $request, string $id): Response
    {
        if ($this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $raw = $this->config->rawSwitch($id) ?? throw $this->createNotFoundException('Switch inconnu');
            $this->config->deleteSwitch($id);
            $this->addFlash('success', sprintf('%s « %s » supprimé. Il disparaîtra des vues à la prochaine actualisation.',
                ($raw['os'] ?? '') === 'instant' ? 'Cluster Wi-Fi' : 'Switch', ($raw['name'] ?? '') ?: $id));
        }

        return $this->redirectToRoute('app_gestion_switch_admin_index');
    }

    #[Route('/{id}/deplacer/{sens}', name: 'move', requirements: ['id' => self::ID, 'sens' => 'haut|bas'], methods: ['POST'])]
    public function move(Request $request, string $id, string $sens): Response
    {
        if ($this->isCsrfTokenValid('move' . $id, $request->request->get('_token'))) {
            $this->config->moveSwitch($id, $sens === 'haut' ? -1 : 1);
        }

        return $this->redirectToRoute('app_gestion_switch_admin_index', ['_fragment' => 'sw-' . $id]);
    }

    #[Route('/{id}/tester', name: 'test', requirements: ['id' => self::ID], methods: ['POST'])]
    public function test(Request $request, string $id): Response
    {
        $raw = $this->config->rawSwitch($id) ?? throw $this->createNotFoundException('Switch inconnu');
        if ($this->isCsrfTokenValid('test' . $id, $request->request->get('_token'))) {
            $name = ($raw['name'] ?? '') ?: $id;
            $r = $this->collector->testSwitch($id);
            if ($r['ok']) {
                $this->addFlash('success', sprintf('Connexion à « %s » réussie (%s s) : %s.', $name,
                    str_replace('.', ',', (string) $r['duration']),
                    implode(' · ', array_filter([$r['system']['hostname'], $r['system']['platform'], $r['system']['version']]))));
            } else {
                $this->addFlash('danger', sprintf('Connexion à « %s » impossible : %s', $name, $r['error']));
            }
        }

        return $this->redirectToRoute('app_gestion_switch_admin_index', ['_fragment' => 'sw-' . $id]);
    }

    #[Route('/parametres', name: 'settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $defaults = $this->config->get('defaults', []);
        $hasPassword = $this->config->hasOwnPassword('default');
        $form = $this->createForm(SwitchSettingsType::class, [
            'username' => $defaults['username'] ?? '',
            'timeout' => $defaults['timeout'] ?? 15,
            'api_version' => $defaults['api_version'] ?? 'auto',
            'verify_ssl' => (bool) ($defaults['verify_ssl'] ?? false),
            'parallel' => $this->config->get('parallel', 8),
            'other_switches' => implode("\n", array_map(fn($o) => trim(($o['host'] ?? '') . ' ; ' . ($o['name'] ?? ''), ' ;'),
                $this->config->get('other_switches', []))),
        ], ['has_password' => $hasPassword]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $others = $this->parseOtherSwitches((string) $data['other_switches'], $form);
            if (!$hasPassword && ($data['password'] ?? '') === '') {
                $form->get('password')->addError(new FormError('Renseignez le mot de passe par défaut.'));
            }
            if ($form->isValid()) {
                $this->config->saveSettings([
                    'parallel' => (int) $data['parallel'],
                    'defaults' => array_merge($defaults, [
                        'username' => trim($data['username']),
                        'verify_ssl' => (bool) $data['verify_ssl'],
                        'api_version' => $data['api_version'],
                        'timeout' => (int) $data['timeout'],
                    ]),
                    'other_switches' => $others,
                ]);
                if (($data['password'] ?? '') !== '') {
                    $this->config->setPassword('default', $data['password']);
                }
                $this->addFlash('success', 'Paramètres enregistrés.');
                return $this->redirectToRoute('app_gestion_switch_admin_index');
            }
        }

        return $this->render('aruba/admin/settings.html.twig', ['form' => $form->createView()]);
    }

    // ────────────────────────────────────────────────────────────────────────

    /** Enregistre le switch et son mot de passe ; false (avec erreur sur le formulaire) si incomplet. */
    private function saveSwitch(FormInterface $form, array $data, string $kind, bool $hasOwn, array $previous = []): bool
    {
        $password = (string) ($data['password'] ?? '');
        if ($data['password_mode'] === 'own' && !$hasOwn && $password === '') {
            $form->get('password')->addError(new FormError($kind === 'wifi'
                ? 'Renseignez le mot de passe de ce cluster.' : 'Renseignez le mot de passe de ce switch.'));
            return false;
        }
        $os = $kind === 'wifi' ? 'instant' : (($data['os'] ?? '') === 'aos-switch' ? 'aos-switch' : null);
        if ($data['password_mode'] === 'common' && !$this->config->hasOwnPassword('default')) {
            $form->get('password_mode')->addError(new FormError(
                'Aucun mot de passe par défaut : renseignez-le dans les valeurs par défaut.'));
            return false;
        }

        // les clés non gérées par le formulaire (password_file…) sont conservées
        $this->config->saveSwitch(array_merge($previous, [
            'id' => $data['id'],
            'host' => trim($data['host']),
            'name' => trim((string) $data['name']),
            'site' => trim((string) $data['site']),
            'os' => $os,
            'protocol' => $os === 'aos-switch' && ($data['protocol'] ?? '') === 'http' ? 'http' : null,
            'username' => trim((string) $data['username']),
            'timeout' => $data['timeout'] !== null ? (int) $data['timeout'] : null,
            'api_version' => ($data['api_version'] ?? '') ?: null,
            'verify_ssl' => ['yes' => true, 'no' => false][$data['verify_ssl']] ?? null,
        ]));
        if ($data['password_mode'] === 'common') {
            $this->config->removePassword($data['id']);
        } elseif ($password !== '') {
            $this->config->setPassword($data['id'], $password);
        }
        return true;
    }

    /** Valeurs appliquées à la collecte : [libellé, valeur, true si propre au switch] */
    private function effective(array $raw, bool $hasOwn): array
    {
        $d = $this->config->get('defaults', []);
        $pick = fn(string $k, mixed $default) => isset($raw[$k]) && $raw[$k] !== '' ? [$raw[$k], true] : [$d[$k] ?? $default, false];
        [$user, $userOwn] = $pick('username', '');
        [$timeout, $timeoutOwn] = $pick('timeout', 15);
        [$api, $apiOwn] = $pick('api_version', 'auto');
        [$ssl, $sslOwn] = $pick('verify_ssl', false);
        $rows = [
            ['Utilisateur', $user, $userOwn],
            ['Mot de passe', 'enregistré', $hasOwn],
            ['Délai de réponse', $timeout . ' s', $timeoutOwn],
        ];
        if (($raw['os'] ?? 'aos-cx') !== 'instant') {
            $rows[] = ['Version d\'API', $api, $apiOwn];
        }
        if (($raw['os'] ?? 'aos-cx') !== 'aos-switch' || ($raw['protocol'] ?? 'https') === 'https') {
            $rows[] = ['Certificat HTTPS', $ssl ? 'vérifié' : 'non vérifié', $sslOwn];
        }
        return $rows;
    }

    /** « 192.168.1.128 ; sw-chlatour-video » par ligne → [{host, name}] */
    private function parseOtherSwitches(string $text, FormInterface $form): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            [$host, $name] = array_map('trim', explode(';', $line, 2)) + [1 => ''];
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]{0,252}$/', $host)) {
                $form->get('other_switches')->addError(new FormError(sprintf('Ligne %d : adresse « %s » invalide.', $n + 1, $host)));
                continue;
            }
            $out[] = array_filter(['host' => $host, 'name' => $name], fn($v) => $v !== '');
        }
        return $out;
    }
}
