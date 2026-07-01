<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\Equipe;
use App\Entity\tracabilite\Ouvrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OuvrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ouvrier::class);
    }

    /** Tous les ouvriers triés par équipe puis par nom */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.equipe', 'e')
            ->addSelect('e')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('o.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Ouvriers d'une équipe donnée */
    public function findByEquipe(Equipe $equipe): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.equipe = :equipe')
            ->setParameter('equipe', $equipe)
            ->orderBy('o.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Ouvriers par nom d'équipe (utile depuis le chefNom des saisies) */
    public function findByEquipeNom(string $nomEquipe): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.equipe', 'e')
            ->where('e.nom = :nom')
            ->setParameter('nom', $nomEquipe)
            ->orderBy('o.nomComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Noms complets distincts pour les filtres (ex : select dropdown) */
    public function findAllNoms(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.nomComplet')
            ->orderBy('o.nomComplet', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findByNomComplet(string $nomComplet): ?Ouvrier
    {
        return $this->findOneBy(['nomComplet' => $nomComplet]);
    }
}
