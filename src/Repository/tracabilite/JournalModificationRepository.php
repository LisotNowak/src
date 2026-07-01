<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\JournalModification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class JournalModificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalModification::class);
    }

    /** Entrées récentes du journal, triées du plus récent au plus ancien */
    public function findRecentes(int $limit = 500): array
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.effectueA', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Historique complet d'une saisie */
    public function findBySaisieId(string $saisieId): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.saisieId = :id')
            ->setParameter('id', $saisieId)
            ->orderBy('j.effectueA', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Toutes les entrées pour un chef sur un mois donné */
    public function findByChefEtMois(string $chefNom, string $mois): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.chefNom = :chef')
            ->andWhere('j.saisieDate LIKE :mois')
            ->setParameter('chef', $chefNom)
            ->setParameter('mois', $mois.'%')
            ->orderBy('j.effectueA', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Nombre d'entrées par action (Création / Modification / Suppression) */
    public function countByAction(): array
    {
        return $this->createQueryBuilder('j')
            ->select('j.action, COUNT(j.id) AS nb')
            ->groupBy('j.action')
            ->getQuery()
            ->getArrayResult();
    }
}
