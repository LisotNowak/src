<?php

namespace App\Controller\tracabilite;

use App\Entity\tracabilite\CartePerso;
use App\Repository\tracabilite\CartePersoRepository;
use App\Repository\tracabilite\ParcelleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tracabilite/cartes-perso', name: 'app_tracabilite_carte_perso_')]
#[IsGranted('ROLE_MANAGER_TRACABILITE')]
class CartePersoController extends AbstractController
{
    private const OPTIONS_DEFAUT = [
        'afficherNoms' => true,
        'taillePolice' => 11,
        'couleur'      => 'neutre',      // neutre | avancement
        'format'       => 'a4-paysage',  // a4-paysage | a4-portrait | a3-paysage | a3-portrait
    ];

    public function __construct(
        private readonly CartePersoRepository $carteRepo,
        private readonly ParcelleRepository   $parcelleRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $cartes = $this->carteRepo->findAllSorted();
        $carte  = $request->query->has('id') ? $this->carteRepo->find($request->query->getInt('id')) : null;
        $carte ??= $cartes[0] ?? null;

        return $this->render('tracabilite/carte_perso/index.html.twig', [
            'active_link' => 'carte_perso',
            'cartes'      => $cartes,
            'carte'       => $carte,
            'options'     => $carte ? array_merge(self::OPTIONS_DEFAUT, $carte->getOptions()) : self::OPTIONS_DEFAUT,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('carte_perso_new', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_tracabilite_carte_perso_index');
        }
        $nom = trim((string) $request->request->get('nom'));
        if ($nom === '') {
            $this->addFlash('danger', 'Le nom de la carte est requis.');
            return $this->redirectToRoute('app_tracabilite_carte_perso_index');
        }

        $carte = (new CartePerso())
            ->setNom(mb_substr($nom, 0, 150))
            ->setOptions(self::OPTIONS_DEFAUT)
            ->setAuteur($this->getUser()?->getUserIdentifier())
            ->setCreeA(new \DateTimeImmutable());
        $this->em->persist($carte);
        $this->em->flush();

        $this->addFlash('success', "Carte « {$carte->getNom()} » créée : cochez les parcelles à afficher puis déplacez-les.");
        return $this->redirectToRoute('app_tracabilite_carte_perso_index', ['id' => $carte->getId()]);
    }

    #[Route('/{id}/save', name: 'save', methods: ['POST'])]
    public function save(int $id, Request $request): JsonResponse
    {
        $carte = $this->carteRepo->find($id);
        if (!$carte) {
            return $this->json(['error' => 'Carte introuvable.'], 404);
        }
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !$this->isCsrfTokenValid('carte_perso_save_'.$id, $data['_token'] ?? '')) {
            return $this->json(['error' => 'Requête invalide, rechargez la page.'], 400);
        }

        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom !== '') {
            $carte->setNom(mb_substr($nom, 0, 150));
        }
        $carte->setDisposition($this->nettoyerDisposition($data['disposition'] ?? []));
        $carte->setOptions($this->nettoyerOptions($data['options'] ?? []));
        $carte->setModifieA(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json([
            'ok'       => true,
            'nom'      => $carte->getNom(),
            'modifieA' => $carte->getModifieA()->format('d/m/Y H:i'),
        ]);
    }

    #[Route('/{id}/dupliquer', name: 'dupliquer', methods: ['POST'])]
    public function dupliquer(int $id, Request $request): Response
    {
        $source = $this->carteRepo->find($id);
        if (!$source || !$this->isCsrfTokenValid('carte_perso_dupliquer_'.$id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Impossible de dupliquer cette carte.');
            return $this->redirectToRoute('app_tracabilite_carte_perso_index');
        }

        $copie = (new CartePerso())
            ->setNom(mb_substr($source->getNom() . ' (copie)', 0, 150))
            ->setDisposition($source->getDisposition())
            ->setOptions($source->getOptions())
            ->setAuteur($this->getUser()?->getUserIdentifier())
            ->setCreeA(new \DateTimeImmutable());
        $this->em->persist($copie);
        $this->em->flush();

        $this->addFlash('success', "Carte dupliquée : « {$copie->getNom()} ».");
        return $this->redirectToRoute('app_tracabilite_carte_perso_index', ['id' => $copie->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $carte = $this->carteRepo->find($id);
        if ($carte && $this->isCsrfTokenValid('carte_perso_delete_'.$id, $request->request->get('_token'))) {
            $this->em->remove($carte);
            $this->em->flush();
            $this->addFlash('success', 'Carte supprimée.');
        }
        return $this->redirectToRoute('app_tracabilite_carte_perso_index');
    }

    /** Ne garde que les parcelles existantes, avec un décalage numérique arrondi au décimètre */
    private function nettoyerDisposition(mixed $disposition): array
    {
        if (!is_array($disposition)) {
            return [];
        }
        $slugs = array_flip(array_map(fn($p) => $p->getSlug(), $this->parcelleRepo->findAvecGeometrie()));

        $propre = [];
        foreach ($disposition as $slug => $decalage) {
            if (!isset($slugs[$slug]) || !is_array($decalage) || count($decalage) !== 2) {
                continue;
            }
            [$dx, $dy] = array_values($decalage);
            if (!is_numeric($dx) || !is_numeric($dy)) {
                continue;
            }
            $propre[(string) $slug] = [round((float) $dx, 1), round((float) $dy, 1)];
        }
        return $propre;
    }

    private function nettoyerOptions(mixed $options): array
    {
        $options = is_array($options) ? $options : [];
        return [
            'afficherNoms' => (bool) ($options['afficherNoms'] ?? self::OPTIONS_DEFAUT['afficherNoms']),
            'taillePolice' => max(6, min(24, (int) ($options['taillePolice'] ?? self::OPTIONS_DEFAUT['taillePolice']))),
            'couleur'      => in_array($options['couleur'] ?? '', ['neutre', 'avancement'], true)
                ? $options['couleur'] : self::OPTIONS_DEFAUT['couleur'],
            'format'       => in_array($options['format'] ?? '', ['a4-paysage', 'a4-portrait', 'a3-paysage', 'a3-portrait'], true)
                ? $options['format'] : self::OPTIONS_DEFAUT['format'],
        ];
    }
}
