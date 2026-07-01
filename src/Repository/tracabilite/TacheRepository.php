<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\Tache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tache::class);
    }

    /** Toutes les tâches triées par nom */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Uniquement les tâches terrain (avec parcelle) */
    public function findTachesTerrain(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.sansParcel = false')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Uniquement les tâches RH/administratives (sans parcelle) */
    public function findTachesRH(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.sansParcel = true')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByNom(string $nom): ?Tache
    {
        return $this->findOneBy(['nom' => $nom]);
    }
}
