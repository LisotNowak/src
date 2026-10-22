<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\Saisie;
use App\Service\tracabilite\EquipeAccess;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class SaisieRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EquipeAccess $equipeAccess,
    ) {
        parent::__construct($registry, Saisie::class);
    }

    /**
     * Point d'entrée de toutes les requêtes : limite aux équipes de l'utilisateur connecté.
     * Les requêtes doivent ensuite utiliser andWhere() (where() effacerait cette restriction).
     */
    private function qb(): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');
        $chefs = $this->equipeAccess->chefsAutorises();
        if ($chefs !== null) {
            if ($chefs === []) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('s.chefNom IN (:chefsAutorises)')->setParameter('chefsAutorises', $chefs);
            }
        }
        return $qb;
    }

    /**
     * Saisies filtrées selon les critères de la page avancement/rendement.
     *
     * @param array{mois?: string, chef?: string, tache?: string, parcel?: string} $filtres
     */
    public function findFiltered(array $filtres): array
    {
        $qb = $this->qb()
            ->orderBy('s.dateTravail', 'DESC')
            ->addOrderBy('s.creeA', 'DESC');

        if (!empty($filtres['mois'])) {
            $qb->andWhere('s.mois = :mois')->setParameter('mois', $filtres['mois']);
        }
        if (!empty($filtres['chef'])) {
            $qb->andWhere('s.chefNom = :chef')->setParameter('chef', $filtres['chef']);
        }
        if (!empty($filtres['tache'])) {
            $qb->andWhere('s.tacheNom = :tache')->setParameter('tache', $filtres['tache']);
        }
        if (!empty($filtres['parcel'])) {
            $qb->andWhere('s.parcelleNom = :parcel')
               ->setParameter('parcel', $filtres['parcel']);
        }

        return $qb->getQuery()->getResult();
    }

    /** Saisies terrain uniquement (avec parcelle, hors RH) */
    public function findTerrain(array $filtres = []): array
    {
        $filtres['type'] = 'Terrain';
        $qb = $this->qb()
            ->andWhere('s.type = :type')
            ->setParameter('type', 'Terrain')
            ->orderBy('s.dateTravail', 'DESC');

        if (!empty($filtres['mois'])) {
            $qb->andWhere('s.mois = :mois')->setParameter('mois', $filtres['mois']);
        }
        if (!empty($filtres['chef'])) {
            $qb->andWhere('s.chefNom = :chef')->setParameter('chef', $filtres['chef']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Avancement par parcelle+tâche : max(avancement) groupé.
     * Retourne des tableaux ['parcelleNom', 'tacheNom', 'maxAdv', 'piedsTotal', 'totalHeures'].
     */
    public function getAvancementParParcelle(string $mois, string $chef = '', string $tache = ''): array
    {
        $qb = $this->qb()
            ->select(
                's.parcelleNom',
                's.tacheNom',
                'MAX(s.avancement) AS maxAdv',
                'MAX(s.piedsTotal) AS piedsTotal',
                'SUM(s.heures) AS totalHeures',
                'MAX(s.dateTravail) AS dernierDate'
            )
            ->andWhere('s.type = :type')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->setParameter('type', 'Terrain')
            ->groupBy('s.parcelleNom', 's.tacheNom')
            ->orderBy('s.parcelleNom', 'ASC')
            ->addOrderBy('s.tacheNom', 'ASC');

        // Mois vide = tous les mois
        if ($mois !== '') {
            $qb->andWhere('s.mois = :mois')->setParameter('mois', $mois);
        }
        if ($chef !== '') {
            $qb->andWhere('s.chefNom = :chef')->setParameter('chef', $chef);
        }
        if ($tache !== '') {
            $qb->andWhere('s.tacheNom = :tache')->setParameter('tache', $tache);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Rendement par personne+tâche sur un mois.
     * Ne retourne que les lignes avec pieds > 0.
     */
    public function getRendementParPersonne(string $mois, string $chef = '', string $tache = ''): array
    {
        $qb = $this->qb()
            ->select(
                's.personnelNom',
                's.personnelContrat',
                's.chefNom',
                's.tacheNom',
                'SUM(s.heures) AS totalHeures',
                'SUM(s.pieds) AS totalPieds'
            )
            ->andWhere('s.mois = :mois')
            ->andWhere('s.pieds > 0')
            ->andWhere('s.heures > 0')
            ->setParameter('mois', $mois)
            ->groupBy('s.personnelNom', 's.personnelContrat', 's.chefNom', 's.tacheNom')
            ->orderBy('s.personnelNom', 'ASC');

        if ($chef !== '') {
            $qb->andWhere('s.chefNom = :chef')->setParameter('chef', $chef);
        }
        if ($tache !== '') {
            $qb->andWhere('s.tacheNom = :tache')->setParameter('tache', $tache);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Avancement pour la carte : MAX d'avancement par parcelle+tâche.
     * Mois vide = toutes périodes.
     */
    public function getAvancementGlobal(string $mois = ''): array
    {
        $qb = $this->qb()
            ->select(
                's.parcelleNom',
                's.tacheNom',
                'MAX(s.avancement) AS maxAdv',
                'MAX(s.dateTravail) AS dernierDate'
            )
            ->andWhere('s.type = :type')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->setParameter('type', 'Terrain')
            ->groupBy('s.parcelleNom', 's.tacheNom')
            ->orderBy('s.parcelleNom', 'ASC');

        if ($mois !== '') {
            $qb->andWhere('s.mois = :mois')->setParameter('mois', $mois);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /** Parcelles commencées mais pas terminées (avancement < 100 et > 0) */
    public function findParcellesNonTerminees(string $mois = '', string $tache = ''): array
    {
        $qb = $this->qb()
            ->select(
                's.parcelleNom',
                's.tacheNom',
                's.chefNom',
                'MAX(s.avancement) AS maxAdv',
                'MAX(s.piedsTotal) AS piedsTotal',
                'MAX(s.dateTravail) AS dernierDate'
            )
            ->andWhere('s.type = :type')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->setParameter('type', 'Terrain')
            ->groupBy('s.parcelleNom', 's.tacheNom', 's.chefNom')
            ->having('MAX(s.avancement) < 100')
            ->andHaving('MAX(s.avancement) > 0')
            ->orderBy('dernierDate', 'ASC');

        if ($mois !== '') {
            $qb->andWhere('s.mois = :mois')->setParameter('mois', $mois);
        }
        if ($tache !== '') {
            $qb->andWhere('s.tacheNom = :tache')->setParameter('tache', $tache);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /** Valeurs distinctes utilisées dans les saisies, pour alimenter les filtres */
    public function findDistinctChefs(): array
    {
        return $this->qb()
            ->select('DISTINCT s.chefNom')
            ->andWhere('s.chefNom IS NOT NULL')
            ->orderBy('s.chefNom', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findDistinctTaches(): array
    {
        return $this->qb()
            ->select('DISTINCT s.tacheNom')
            ->andWhere('s.tacheNom IS NOT NULL')
            ->orderBy('s.tacheNom', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Tâches de terrain présentes dans les saisies d'un mois (mois vide = tous les mois).
     * $avecPieds : uniquement les tâches ayant des pieds et des heures (page rendement).
     * $parcelles : limite aux tâches réalisées sur ces parcelles (null = toutes les parcelles).
     */
    public function findTachesTerrainSurPeriode(string $mois, bool $avecPieds = false, ?array $parcelles = null): array
    {
        if ($parcelles === []) {
            return [];
        }

        $qb = $this->qb()
            ->select('DISTINCT s.tacheNom')
            ->andWhere('s.type = :type')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->setParameter('type', 'Terrain')
            ->orderBy('s.tacheNom', 'ASC');

        if ($mois !== '') {
            $qb->andWhere('s.mois = :mois')->setParameter('mois', $mois);
        }
        if ($avecPieds) {
            $qb->andWhere('s.pieds > 0')->andWhere('s.heures > 0');
        }
        if ($parcelles !== null) {
            $qb->andWhere('s.parcelleNom IN (:parcelles)')->setParameter('parcelles', $parcelles);
        }

        return $qb->getQuery()->getSingleColumnResult();
    }

    public function findDistinctParcelles(): array
    {
        return $this->qb()
            ->select('DISTINCT s.parcelleNom')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->orderBy('s.parcelleNom', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /** Saisies récentes pour la page d'accueil, limitées */
    public function findRecentes(int $limit = 200): array
    {
        return $this->qb()
            ->orderBy('s.creeA', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** KPIs globaux pour un mois */
    public function getKpis(string $mois): array
    {
        $result = $this->qb()
            ->select(
                'COUNT(s.id) AS nbSaisies',
                'SUM(s.heures) AS totalHeures',
                'COUNT(DISTINCT s.parcelleNom) AS nbParcelles'
            )
            ->andWhere('s.mois = :mois')
            ->setParameter('mois', $mois)
            ->getQuery()
            ->getSingleResult();

        return $result;
    }
}
