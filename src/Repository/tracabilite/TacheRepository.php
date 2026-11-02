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

    /** Toutes les tâches triées par nom, avec leur type et les équipes de ce type */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.type', 'ty')->addSelect('ty')
            ->leftJoin('ty.equipes', 'e')->addSelect('e')
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
