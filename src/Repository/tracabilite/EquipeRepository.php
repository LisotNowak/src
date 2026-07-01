<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\Equipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EquipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipe::class);
    }

    /** Retourne toutes les équipes triées par nom */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByNom(string $nom): ?Equipe
    {
        return $this->findOneBy(['nom' => $nom]);
    }

    /** Équipes avec leur liste d'ouvriers en une seule requête */
    public function findAllWithOuvriers(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.ouvriers', 'o')
            ->addSelect('o')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('o.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
