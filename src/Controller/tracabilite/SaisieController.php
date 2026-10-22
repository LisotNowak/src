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
use App\Service\tracabilite\EquipeAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tracabilite/saisie', name: 'app_tracabilite_saisie_')]
#[IsGranted('ROLE_MANAGER_TRACABILITE')]
class SaisieController extends AbstractController
{
    private const SAISONNIERS = 'Saisonniers non nominatifs';

    public function __construct(
        private readonly SaisieRepository   $saisieRepo,
        private readonly EquipeRepository   $equipeRepo,
        private readonly OuvrierRepository  $ouvrierRepo,
        private readonly TacheRepository    $tacheRepo,
        private readonly ParcelleRepository $parcelleRepo,
        private readonly EquipeAccess       $equipeAccess,
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

        $multiParcelle   = $request->request->getBoolean('multiParcelle');
        $parcellesNoms   = $this->parcellesNomsFromRequest($request);
        $heuresParcelles = $this->heuresParcellesFromRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tache = $this->tacheRepo->findByNom((string) $saisie->getTacheNom());
            $multi = $multiParcelle && !($tache && $tache->isSansParcel());
            $parcelles = $multi ? $this->parcellesFromNoms($parcellesNoms) : [];

            if (!$this->setPersonnelFromRequest($request, $saisie)) {
                $this->addFlash('danger', 'Veuillez sélectionner au moins une personne.');
            } elseif ($multi && !$parcelles) {
                $this->addFlash('danger', 'Veuillez sélectionner au moins une parcelle.');
            } elseif ($multi && $this->parcellesSansHeures($parcelles, $heuresParcelles)) {
                $this->addFlash('danger', 'Indiquez un nombre d\'heures supérieur à 0 pour chaque parcelle : '
                    . implode(', ', $this->parcellesSansHeures($parcelles, $heuresParcelles)) . '.');
            } elseif (!$multi && !$this->checkParcelle($form)) {
                // L'erreur est affichée sous le champ Parcelle
            } elseif ($multi) {
                $nb = $this->creerSaisiesMultiParcelles($saisie, $parcelles, $heuresParcelles);
                $this->em->flush();

                $this->addFlash('success', $nb . ' saisie(s) créée(s), une par parcelle, avec un avancement de 100 %.');
                return $this->redirectToRoute('app_tracabilite_saisie_index');
            } else {
                $this->hydrateSaisie($saisie);
                $saisie->setId($this->uuid4());
                $saisie->setCreeA(new \DateTimeImmutable());

                $this->em->persist($saisie);
                $this->em->persist($this->makeJournalEntry('Création', $saisie, null));
                $this->em->flush();

                $this->addFlash('success', 'Saisie créée avec succès.');
                return $this->redirectToRoute('app_tracabilite_saisie_index');
            }
        }

        return $this->render('tracabilite/saisie/new.html.twig', [
            'active_link'   => 'saisie',
            'form'          => $form,
            'multiParcelle'   => $multiParcelle,
            'parcellesNoms'   => $parcellesNoms,
            'heuresParcelles' => $heuresParcelles,
            'nbSaisonniers' => $this->nbSaisonniersFromRequest($request),
            'tachesJson'    => $this->buildTachesJson(),
            'parcellesJson' => $this->buildParcellesJson(),
            'equipesJson'   => $this->buildEquipesJson(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request): Response
    {
        $saisie = $this->saisieRepo->find($id);
        if (!$saisie || !$this->equipeAccess->peutVoirChef($saisie->getChefNom())) {
            throw $this->createNotFoundException('Saisie introuvable.');
        }

        $avant = $this->snapshot($saisie);

        $form = $this->createForm(SaisieType::class, $saisie, $this->buildFormOptions());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->setPersonnelFromRequest($request, $saisie)) {
                $this->addFlash('danger', 'Veuillez sélectionner au moins une personne.');
            } elseif (!$this->checkParcelle($form)) {
                // L'erreur est affichée sous le champ Parcelle
            } else {
                $this->hydrateSaisie($saisie);
                $saisie->setModifieA(new \DateTime());
                $saisie->setNombreModifs($saisie->getNombreModifs() + 1);

                $this->em->persist($this->makeJournalEntry('Modification', $saisie, $avant));
                $this->em->flush();

                $this->addFlash('success', 'Saisie modifiée avec succès.');
                return $this->redirectToRoute('app_tracabilite_saisie_index');
            }
        }

        $personnelsActuels = array_map('trim', explode(', ', $saisie->getPersonnelNom() ?? ''));

        return $this->render('tracabilite/saisie/edit.html.twig', [
            'active_link'      => 'saisie',
            'form'             => $form,
            'saisie'           => $saisie,
            'personnelsActuels'=> $personnelsActuels,
            'nbSaisonniers'    => $form->isSubmitted()
                ? $this->nbSaisonniersFromRequest($request)
                : $this->nbSaisonniersFromSaisie($saisie, $personnelsActuels),
            'tachesJson'       => $this->buildTachesJson(),
            'parcellesJson'    => $this->buildParcellesJson(),
            'equipesJson'      => $this->buildEquipesJson(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $saisie = $this->saisieRepo->find($id);
        if (!$saisie || !$this->equipeAccess->peutVoirChef($saisie->getChefNom())) {
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
        $equipeNom = urldecode($equipeNom);
        if (!$this->equipeAccess->peutVoirChef($equipeNom)) {
            return $this->json(['error' => 'Équipe introuvable'], 404);
        }
        $ouvriers = $this->ouvrierRepo->findByEquipeNom($equipeNom);
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
        return [
            'equipes'   => array_map(fn($e) => $e->getNom(), $this->equipeAccess->equipesVisibles()),
            'taches'    => array_map(fn($t) => $t->getNom(), $this->tacheRepo->findAllSorted()),
            'parcelles' => array_map(fn($p) => $p->getSlug(), $this->parcelleRepo->findAllSorted()),
        ];
    }

    private function setPersonnelFromRequest(Request $request, \App\Entity\tracabilite\Saisie $saisie): bool
    {
        $noms = array_values(array_filter(
            array_map('trim', $request->request->all()['personnelsNoms'] ?? [])
        ));
        if (empty($noms)) {
            return false;
        }
        $effectif = count($noms);
        // « Saisonniers non nominatifs » compte pour le nombre de saisonniers indiqué
        if (in_array(self::SAISONNIERS, $noms, true)) {
            $effectif += $this->nbSaisonniersFromRequest($request) - 1;
        }
        $saisie->setPersonnelNom(implode(', ', $noms));
        $saisie->setEffectif($effectif);
        return true;
    }

    /** @return string[] */
    private function parcellesNomsFromRequest(Request $request): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', $request->request->all()['parcellesNoms'] ?? [])
        )));
    }

    /** @return \App\Entity\tracabilite\Parcelle[] */
    private function parcellesFromNoms(array $slugs): array
    {
        $parcelles = [];
        foreach ($slugs as $slug) {
            if ($p = $this->parcelleRepo->findBySlug($slug)) {
                $parcelles[] = $p;
            }
        }
        return $parcelles;
    }

    /**
     * Ventilation des heures par parcelle : heuresParcelles[slug] = heures.
     * @return array<string, float>
     */
    private function heuresParcellesFromRequest(Request $request): array
    {
        $heures = [];
        foreach ($request->request->all()['heuresParcelles'] ?? [] as $slug => $valeur) {
            $heures[(string) $slug] = round((float) str_replace(',', '.', (string) $valeur), 2);
        }
        return $heures;
    }

    /** @return string[] slugs des parcelles sans heures ventilées */
    private function parcellesSansHeures(array $parcelles, array $heuresParcelles): array
    {
        return array_values(array_map(
            fn($p) => $p->getSlug(),
            array_filter($parcelles, fn($p) => ($heuresParcelles[$p->getSlug()] ?? 0) <= 0)
        ));
    }

    /**
     * Saisie multi-parcelles : une saisie par parcelle, chacune terminée (avancement 100 %),
     * avec les heures ventilées sur cette parcelle.
     */
    private function creerSaisiesMultiParcelles(Saisie $modele, array $parcelles, array $heuresParcelles): int
    {
        foreach ($parcelles as $parcelle) {
            $pieds = (float) ($parcelle->getNombrePieds() ?? 0);

            $s = clone $modele;
            $s->setDateTravail(clone $modele->getDateTravail());
            $s->setParcelleNom($parcelle->getSlug());
            $s->setHeures($heuresParcelles[$parcelle->getSlug()]);
            $s->setAvancement(100.0);
            $s->setPieds($pieds);
            $s->setPiedsTotal($pieds);
            $this->hydrateSaisie($s);
            $s->setId($this->uuid4());
            $s->setCreeA(new \DateTimeImmutable());

            $this->em->persist($s);
            $this->em->persist($this->makeJournalEntry('Création', $s, null));
        }

        return count($parcelles);
    }

    /** Une tâche de terrain doit avoir une parcelle */
    private function checkParcelle(FormInterface $form): bool
    {
        $tache = $this->tacheRepo->findByNom((string) $form->get('tacheNom')->getData());
        if ($tache && !$tache->isSansParcel() && !$form->get('parcelleNom')->getData()) {
            $form->get('parcelleNom')->addError(new FormError('Veuillez choisir une parcelle pour cette tâche.'));
            return false;
        }
        return true;
    }

    private function nbSaisonniersFromRequest(Request $request): int
    {
        return max(1, min(999, (int) $request->request->get('nbSaisonniers', 1)));
    }

    /** Nombre de saisonniers non nominatifs représentés par une saisie existante */
    private function nbSaisonniersFromSaisie(Saisie $saisie, array $noms): int
    {
        if (!in_array(self::SAISONNIERS, $noms, true)) {
            return 1;
        }
        return max(1, $saisie->getEffectif() - (count($noms) - 1));
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

        $premierNom = trim(explode(',', $saisie->getPersonnelNom() ?? '')[0]);
        $ouvrier = $this->ouvrierRepo->findByNomComplet($premierNom);
        if ($ouvrier) {
            $saisie->setPersonnelContrat($ouvrier->getContrat());
        }
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
        // Avancement actuel de chaque tâche, une parcelle pouvant avoir plusieurs tâches en parallèle
        $advTaches = [];
        foreach ($this->saisieRepo->getAvancementGlobal() as $a) {
            $advTaches[$a['parcelleNom']][$a['tacheNom']] = (float) $a['maxAdv'];
        }

        $data = [];
        foreach ($this->parcelleRepo->findAllSorted() as $p) {
            $data[$p->getSlug()] = [
                'nombrePieds' => $p->getNombrePieds(),
                'cepage'      => $p->getCepage(),
                'avancements' => (object) ($advTaches[$p->getSlug()] ?? []),
            ];
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function buildEquipesJson(): string
    {
        $data = [];
        foreach ($this->equipeRepo->findAllWithOuvriers() as $e) {
            if (!$this->equipeAccess->peutVoirChef($e->getNom())) {
                continue;
            }
            $ouvriers = array_map(
                fn($o) => ['nom' => $o->getNomComplet(), 'contrat' => $o->getContrat()],
                $e->getOuvriers()->toArray()
            );
            // Saisonniers non nominatifs toujours disponibles dans chaque équipe
            $ouvriers[] = ['nom' => self::SAISONNIERS, 'contrat' => 'Saisonnier'];
            $data[$e->getNom()] = $ouvriers;
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
