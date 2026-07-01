<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\Parcelle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ParcelleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcelle::class);
    }

    /** Toutes les parcelles triées par slug */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?Parcelle
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** Parcelles ayant un nombre de pieds renseigné */
    public function findAvecPieds(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nombrePieds IS NOT NULL')
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Parcelles par cépage */
    public function findByCepage(string $cepage): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.cepage = :cepage')
            ->setParameter('cepage', $cepage)
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Parcelles avec géométrie (pour la carte) */
    public function findAvecGeometrie(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.geometrie IS NOT NULL')
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
