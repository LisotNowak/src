<?php

namespace App\Repository\dotation;

use App\Entity\dotation\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }


    public function updateStockQuantity(string $referenceArticle, string $nomTaille, string $nomCouleur, int $quantity): void
    {
        $qb = $this->createQueryBuilder('s')
            ->update()
            ->set('s.stock', ':quantity')
            ->where('s.referenceArticle = :referenceArticle')
            ->andWhere('s.nomTaille = :nomTaille')
            ->andWhere('s.nomCouleur = :nomCouleur')
            ->setParameter('quantity', $quantity)
            ->setParameter('referenceArticle', $referenceArticle)
            ->setParameter('nomTaille', $nomTaille)
            ->setParameter('nomCouleur', $nomCouleur);

        $qb->getQuery()->execute();
    }

    //    /**
    //     * @return Stock[] Returns an array of Stock objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Stock
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
