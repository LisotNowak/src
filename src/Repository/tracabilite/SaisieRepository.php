<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\Saisie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SaisieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saisie::class);
    }

    /**
     * Saisies filtrées selon les critères de la page avancement/rendement.
     *
     * @param array{mois?: string, chef?: string, tache?: string, parcel?: string} $filtres
     */
    public function findFiltered(array $filtres): array
    {
        $qb = $this->createQueryBuilder('s')
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
            $qb->andWhere('s.parcelleNom LIKE :parcel')
               ->setParameter('parcel', '%'.$filtres['parcel'].'%');
        }

        return $qb->getQuery()->getResult();
    }

    /** Saisies terrain uniquement (avec parcelle, hors RH) */
    public function findTerrain(array $filtres = []): array
    {
        $filtres['type'] = 'Terrain';
        $qb = $this->createQueryBuilder('s')
            ->where('s.type = :type')
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
     * Retourne des tableaux ['parcelleNom', 'tacheNom', 'maxAdv', 'totalHeures'].
     */
    public function getAvancementParParcelle(string $mois, string $chef = ''): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select(
                's.parcelleNom',
                's.tacheNom',
                'MAX(s.avancement) AS maxAdv',
                'SUM(s.heures) AS totalHeures',
                'MAX(s.dateTravail) AS dernierDate'
            )
            ->where('s.mois = :mois')
            ->andWhere('s.type = :type')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->setParameter('mois', $mois)
            ->setParameter('type', 'Terrain')
            ->groupBy('s.parcelleNom', 's.tacheNom')
            ->orderBy('s.parcelleNom', 'ASC');

        if ($chef !== '') {
            $qb->andWhere('s.chefNom = :chef')->setParameter('chef', $chef);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Rendement par personne+tâche sur un mois.
     * Ne retourne que les lignes avec pieds > 0.
     */
    public function getRendementParPersonne(string $mois, string $chef = ''): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select(
                's.personnelNom',
                's.personnelContrat',
                's.chefNom',
                's.tacheNom',
                'SUM(s.heures) AS totalHeures',
                'SUM(s.heuresNettes) AS totalHeuresNettes',
                'SUM(s.pieds) AS totalPieds'
            )
            ->where('s.mois = :mois')
            ->andWhere('s.pieds > 0')
            ->andWhere('s.heuresNettes > 0')
            ->setParameter('mois', $mois)
            ->groupBy('s.personnelNom', 's.personnelContrat', 's.chefNom', 's.tacheNom')
            ->orderBy('s.personnelNom', 'ASC');

        if ($chef !== '') {
            $qb->andWhere('s.chefNom = :chef')->setParameter('chef', $chef);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Avancement global (toutes périodes) pour la carte.
     * Retourne le MAX d'avancement par parcelle+tâche, sans filtre de mois.
     */
    public function getAvancementGlobal(): array
    {
        return $this->createQueryBuilder('s')
            ->select(
                's.parcelleNom',
                's.tacheNom',
                'MAX(s.avancement) AS maxAdv',
                'MAX(s.dateTravail) AS dernierDate'
            )
            ->where('s.type = :type')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->setParameter('type', 'Terrain')
            ->groupBy('s.parcelleNom', 's.tacheNom')
            ->orderBy('s.parcelleNom', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** Parcelles commencées mais pas terminées (avancement < 100 et > 0) */
    public function findParcellesNonTerminees(string $mois = ''): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select(
                's.parcelleNom',
                's.tacheNom',
                's.chefNom',
                'MAX(s.avancement) AS maxAdv',
                'MAX(s.dateTravail) AS dernierDate'
            )
            ->where('s.type = :type')
            ->andWhere('s.parcelleNom IS NOT NULL')
            ->setParameter('type', 'Terrain')
            ->groupBy('s.parcelleNom', 's.tacheNom', 's.chefNom')
            ->having('MAX(s.avancement) < 100')
            ->andHaving('MAX(s.avancement) > 0')
            ->orderBy('dernierDate', 'ASC');

        if ($mois !== '') {
            $qb->andWhere('s.mois = :mois')->setParameter('mois', $mois);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /** Saisies récentes pour la page d'accueil, limitées */
    public function findRecentes(int $limit = 200): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.creeA', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** KPIs globaux pour un mois */
    public function getKpis(string $mois): array
    {
        $result = $this->createQueryBuilder('s')
            ->select(
                'COUNT(s.id) AS nbSaisies',
                'SUM(s.heures) AS totalHeures',
                'COUNT(DISTINCT s.parcelleNom) AS nbParcelles'
            )
            ->where('s.mois = :mois')
            ->setParameter('mois', $mois)
            ->getQuery()
            ->getSingleResult();

        return $result;
    }
}
