<?php

namespace App\Repository\client;

use App\Entity\client\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 *
 * @method Categorie|null find($id, $lockMode = null, $lockVersion = null)
 * @method Categorie|null findOneBy(array $criteria, array $orderBy = null)
 * @method Categorie[]    findAll()
 * @method Categorie[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    // Exemple de méthode personnalisée
    // public function findByCategorie(string $nom): array
    // {
    //     return $this->createQueryBuilder('ls')
    //         ->andWhere('ls.Categorie LIKE :nom')
    //         ->setParameter('nom', '%' . $nom . '%')
    //         ->getQuery()
    //         ->getResult();
    // }
}
