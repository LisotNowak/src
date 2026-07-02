<?php

namespace App\Controller\tracabilite;

use App\Entity\tracabilite\JournalModification;
use App\Entity\tracabilite\Saisie;
use App\Form\tracabilite\SaisieType;
use App\Repository\tracabilite\EquipeRepository;
use App\Repository\tracabilite\OuvrierRepository;
use App\Repository\tracabilite\ParcelleRepository;
use App\Repository\tracabilite\SaisieRepository;
use App\Repository\tracabilite\TacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tracabilite/saisie', name: 'app_tracabilite_saisie_')]
class SaisieController extends AbstractController
{
    public function __construct(
        private readonly SaisieRepository   $saisieRepo,
        private readonly EquipeRepository   $equipeRepo,
        private readonly OuvrierRepository  $ouvrierRepo,
        private readonly TacheRepository    $tacheRepo,
        private readonly ParcelleRepository $parcelleRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $filtres = [
            'mois'  => $request->query->get('mois', date('Y-m')),
            'chef'  => $request->query->get('chef', ''),
            'tache' => $request->query->get('tache', ''),
            'parcel'=> $request->query->get('parcel', ''),
        ];

        return $this->render('tracabilite/saisie/index.html.twig', [
            'active_link' => 'saisie',
            'saisies'     => $this->saisieRepo->findFiltered($filtres),
            'equipes'     => $this->saisieRepo->findDistinctChefs(),
            'taches'      => $this->saisieRepo->findDistinctTaches(),
            'parcelles'   => $this->saisieRepo->findDistinctParcelles(),
            'filtres'     => $filtres,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $saisie = new Saisie();
        $saisie->setDateTravail(new \DateTime());
        $saisie->setType('Terrain');

        $form = $this->createForm(SaisieType::class, $saisie, $this->buildFormOptions());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->hydrateSaisie($saisie);
            $saisie->setId($this->uuid4());
            $saisie->setCreeA(new \DateTimeImmutable());

            $this->em->persist($saisie);
            $this->em->persist($this->makeJournalEntry('Création', $saisie, null));
            $this->em->flush();

            $this->addFlash('success', 'Saisie créée avec succès.');
            return $this->redirectToRoute('app_tracabilite_saisie_index');
        }

        return $this->render('tracabilite/saisie/new.html.twig', [
            'active_link'   => 'saisie',
            'form'          => $form,
            'tachesJson'    => $this->buildTachesJson(),
            'parcellesJson' => $this->buildParcellesJson(),
            'equipesJson'   => $this->buildEquipesJson(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request): Response
    {
        $saisie = $this->saisieRepo->find($id);
        if (!$saisie) {
            throw $this->createNotFoundException('Saisie introuvable.');
        }

        $avant = $this->snapshot($saisie);

        $form = $this->createForm(SaisieType::class, $saisie, $this->buildFormOptions());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->hydrateSaisie($saisie);
            $saisie->setModifieA(new \DateTime());
            $saisie->setNombreModifs($saisie->getNombreModifs() + 1);

            $this->em->persist($this->makeJournalEntry('Modification', $saisie, $avant));
            $this->em->flush();

            $this->addFlash('success', 'Saisie modifiée avec succès.');
            return $this->redirectToRoute('app_tracabilite_saisie_index');
        }

        return $this->render('tracabilite/saisie/edit.html.twig', [
            'active_link'   => 'saisie',
            'form'          => $form,
            'saisie'        => $saisie,
            'tachesJson'    => $this->buildTachesJson(),
            'parcellesJson' => $this->buildParcellesJson(),
            'equipesJson'   => $this->buildEquipesJson(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $saisie = $this->saisieRepo->find($id);
        if (!$saisie) {
            throw $this->createNotFoundException('Saisie introuvable.');
        }

        if (!$this->isCsrfTokenValid('delete_saisie_'.$id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_tracabilite_saisie_index');
        }

        $avant = $this->snapshot($saisie);
        $this->em->persist($this->makeJournalEntry('Suppression', $saisie, $avant));
        $this->em->remove($saisie);
        $this->em->flush();

        $this->addFlash('success', 'Saisie supprimée.');
        return $this->redirectToRoute('app_tracabilite_saisie_index');
    }

    #[Route('/api/ouvriers/{equipeNom}', name: 'api_ouvriers', methods: ['GET'])]
    public function apiOuvriers(string $equipeNom): JsonResponse
    {
        $ouvriers = $this->ouvrierRepo->findByEquipeNom(urldecode($equipeNom));
        return $this->json(array_map(
            fn($o) => ['nom' => $o->getNomComplet(), 'contrat' => $o->getContrat()],
            $ouvriers
        ));
    }

    #[Route('/api/parcelle/{slug}', name: 'api_parcelle', methods: ['GET'])]
    public function apiParcelle(string $slug): JsonResponse
    {
        $parcelle = $this->parcelleRepo->findBySlug($slug);
        if (!$parcelle) {
            return $this->json(['error' => 'Parcelle introuvable'], 404);
        }
        return $this->json([
            'nombrePieds' => $parcelle->getNombrePieds(),
            'cepage'      => $parcelle->getCepage(),
            'gamme'       => $parcelle->getGamme(),
        ]);
    }

    // -------------------------------------------------------------------------

    private function buildFormOptions(): array
    {
        $noms = $this->ouvrierRepo->findAllNoms();
        if (!in_array('Saisonniers non nominatifs', $noms, true)) {
            $noms[] = 'Saisonniers non nominatifs';
        }

        return [
            'equipes'   => array_map(fn($e) => $e->getNom(), $this->equipeRepo->findAllSorted()),
            'personnel' => $noms,
            'taches'    => array_map(fn($t) => $t->getNom(), $this->tacheRepo->findAllSorted()),
            'parcelles' => array_map(fn($p) => $p->getSlug(), $this->parcelleRepo->findAllSorted()),
        ];
    }

    private function hydrateSaisie(Saisie $saisie): void
    {
        $saisie->setMois($saisie->getDateTravail()->format('Y-m'));

        $tache = $this->tacheRepo->findByNom($saisie->getTacheNom());
        if ($tache && $tache->isSansParcel()) {
            $saisie->setType('RH');
            $saisie->setParcelleNom(null);
        } else {
            $saisie->setType('Terrain');
        }

        $ouvrier = $this->ouvrierRepo->findByNomComplet($saisie->getPersonnelNom());
        if ($ouvrier) {
            $saisie->setPersonnelContrat($ouvrier->getContrat());
        }

        $minutes = $this->calcMinutesPause($saisie);
        $saisie->setMinutesPause($minutes);
        $saisie->setHeuresNettes(max(0.0, $saisie->getHeures() - ($minutes / 60.0)));
    }

    private function calcMinutesPause(Saisie $saisie): int
    {
        $mois = (int) $saisie->getDateTravail()->format('n');
        return match ($saisie->getModePause()) {
            'hiver'  => 15,
            'ete'    => 20,
            'aucune' => 0,
            'manuel' => $saisie->getMinutesPause(),
            default  => ($mois >= 4 && $mois <= 9) ? 20 : 15,
        };
    }

    private function snapshot(Saisie $s): string
    {
        return json_encode([
            'dateTravail'  => $s->getDateTravail()?->format('Y-m-d'),
            'chefNom'      => $s->getChefNom(),
            'personnelNom' => $s->getPersonnelNom(),
            'tacheNom'     => $s->getTacheNom(),
            'parcelleNom'  => $s->getParcelleNom(),
            'heures'       => $s->getHeures(),
            'avancement'   => $s->getAvancement(),
            'pieds'        => $s->getPieds(),
            'commentaire'  => $s->getCommentaire(),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function makeJournalEntry(string $action, Saisie $saisie, ?string $avant): JournalModification
    {
        $j = new JournalModification();
        $j->setId($this->uuid4());
        $j->setAction($action);
        $j->setEffectueA(new \DateTimeImmutable());
        $j->setSaisieId($saisie->getId());
        $j->setSaisieDate($saisie->getDateTravail()?->format('Y-m-d'));
        $j->setChefNom($saisie->getChefNom());
        $j->setPersonnelNom($saisie->getPersonnelNom());
        $j->setTacheNom($saisie->getTacheNom());
        $j->setParcelleNom($saisie->getParcelleNom());
        $j->setAvant($avant);
        $j->setApres($action !== 'Suppression' ? $this->snapshot($saisie) : null);
        return $j;
    }

    private function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    private function buildTachesJson(): string
    {
        $data = [];
        foreach ($this->tacheRepo->findAllSorted() as $t) {
            $data[$t->getNom()] = ['sansParcel' => $t->isSansParcel()];
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function buildParcellesJson(): string
    {
        $data = [];
        foreach ($this->parcelleRepo->findAllSorted() as $p) {
            $data[$p->getSlug()] = [
                'nombrePieds' => $p->getNombrePieds(),
                'cepage'      => $p->getCepage(),
            ];
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function buildEquipesJson(): string
    {
        $data = [];
        foreach ($this->equipeRepo->findAllWithOuvriers() as $e) {
            $ouvriers = array_map(
                fn($o) => ['nom' => $o->getNomComplet(), 'contrat' => $o->getContrat()],
                $e->getOuvriers()->toArray()
            );
            // Saisonniers non nominatifs toujours disponibles dans chaque équipe
            $ouvriers[] = ['nom' => 'Saisonniers non nominatifs', 'contrat' => 'Saisonnier'];
            $data[$e->getNom()] = $ouvriers;
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
