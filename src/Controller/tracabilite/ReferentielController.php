<?php

namespace App\Controller\tracabilite;

use App\Entity\tracabilite\Equipe;
use App\Entity\tracabilite\Ouvrier;
use App\Entity\tracabilite\Tache;
use App\Repository\tracabilite\EquipeRepository;
use App\Repository\tracabilite\OuvrierRepository;
use App\Repository\tracabilite\ParcelleRepository;
use App\Repository\tracabilite\TacheRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tracabilite/referentiel', name: 'app_tracabilite_referentiel_')]
class ReferentielController extends AbstractController
{
    public function __construct(
        private readonly EquipeRepository   $equipeRepo,
        private readonly OuvrierRepository  $ouvrierRepo,
        private readonly TacheRepository    $tacheRepo,
        private readonly ParcelleRepository $parcelleRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('tracabilite/referentiel/index.html.twig', [
            'active_link' => 'referentiel',
            'equipes'     => $this->equipeRepo->findAllWithOuvriers(),
            'taches'      => $this->tacheRepo->findAllSorted(),
            'parcelles'   => $this->parcelleRepo->findAllSorted(),
        ]);
    }

    // ---- Équipes ----

    #[Route('/equipe/new', name: 'equipe_new', methods: ['POST'])]
    public function equipeNew(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('equipe_new', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $nom = trim((string) $request->request->get('nom'));
        if ($nom === '') {
            $this->addFlash('danger', 'Le nom est requis.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $equipe = (new Equipe())->setNom($nom);
        $this->em->persist($equipe);
        $this->em->flush();
        $this->addFlash('success', "Équipe « {$nom} » créée.");
        return $this->redirectToRoute('app_tracabilite_referentiel_index');
    }

    #[Route('/equipe/{id}/delete', name: 'equipe_delete', methods: ['POST'])]
    public function equipeDelete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('equipe_delete_'.$id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $equipe = $this->equipeRepo->find($id);
        if ($equipe) {
            $this->em->remove($equipe);
            $this->em->flush();
            $this->addFlash('success', 'Équipe supprimée.');
        }
        return $this->redirectToRoute('app_tracabilite_referentiel_index');
    }

    // ---- Ouvriers ----

    #[Route('/ouvrier/new', name: 'ouvrier_new', methods: ['POST'])]
    public function ouvrierNew(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('ouvrier_new', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $nom      = trim((string) $request->request->get('nom'));
        $contrat  = $request->request->get('contrat', 'Permanent');
        $equipeId = (int) $request->request->get('equipe_id');

        if ($nom === '') {
            $this->addFlash('danger', 'Le nom est requis.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $equipe  = $this->equipeRepo->find($equipeId);
        $ouvrier = (new Ouvrier())->setNomComplet($nom)->setContrat($contrat)->setEquipe($equipe);
        $this->em->persist($ouvrier);
        $this->em->flush();
        $this->addFlash('success', "Ouvrier « {$nom} » ajouté.");
        return $this->redirectToRoute('app_tracabilite_referentiel_index');
    }

    #[Route('/ouvrier/{id}/delete', name: 'ouvrier_delete', methods: ['POST'])]
    public function ouvrierDelete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('ouvrier_delete_'.$id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $ouvrier = $this->ouvrierRepo->find($id);
        if ($ouvrier) {
            $this->em->remove($ouvrier);
            $this->em->flush();
            $this->addFlash('success', 'Ouvrier supprimé.');
        }
        return $this->redirectToRoute('app_tracabilite_referentiel_index');
    }

    // ---- Tâches ----

    #[Route('/tache/new', name: 'tache_new', methods: ['POST'])]
    public function tacheNew(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tache_new', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $nom       = trim((string) $request->request->get('nom'));
        $sansParcel = (bool) $request->request->get('sans_parcel', false);

        if ($nom === '') {
            $this->addFlash('danger', 'Le nom est requis.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $tache = (new Tache())->setNom($nom)->setSansParcel($sansParcel);
        $this->em->persist($tache);
        $this->em->flush();
        $this->addFlash('success', "Tâche « {$nom} » créée.");
        return $this->redirectToRoute('app_tracabilite_referentiel_index');
    }

    #[Route('/tache/{id}/delete', name: 'tache_delete', methods: ['POST'])]
    public function tacheDelete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tache_delete_'.$id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_tracabilite_referentiel_index');
        }
        $tache = $this->tacheRepo->find($id);
        if ($tache) {
            $this->em->remove($tache);
            $this->em->flush();
            $this->addFlash('success', 'Tâche supprimée.');
        }
        return $this->redirectToRoute('app_tracabilite_referentiel_index');
    }
}
