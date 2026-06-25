<?php

namespace App\Repository\Inventaire;

use App\Entity\Inventaire\StockArticle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockArticle>
 */
class StockArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockArticle::class);
    }

    /**
     * @return StockArticle[]
     */
    public function findByDepot(string $depot): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.depot = :depot')
            ->setParameter('depot', $depot)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return StockArticle[]
     */
    public function findByEmplacement(string $emplacement): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.emplacement = :emplacement')
            ->setParameter('emplacement', $emplacement)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return StockArticle[]
     */
    public function findByUniteMesure(string $uniteMesure): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.uniteMesure = :um')
            ->setParameter('um', $uniteMesure)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return StockArticle[]
     */
    public function search(string $terme): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.codeArticle LIKE :t OR s.nom LIKE :t')
            ->setParameter('t', '%' . $terme . '%')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCodeAndLot(string $codeArticle, ?string $numeroLot, ?string $emplacement): ?StockArticle
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.codeArticle = :code')
            ->setParameter('code', $codeArticle);

        if ($numeroLot !== null) {
            $qb->andWhere('s.numeroLot = :lot')->setParameter('lot', $numeroLot);
        } else {
            $qb->andWhere('s.numeroLot IS NULL');
        }

        if ($emplacement !== null) {
            $qb->andWhere('s.emplacement = :empl')->setParameter('empl', $emplacement);
        } else {
            $qb->andWhere('s.emplacement IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Recherche filtrée multi-critères avec pagination.
     *
     * @return array{items: StockArticle[], total: int, depots: string[], emplacements: string[], unites: string[]}
     */
    public function findWithFilters(
        ?string $depot,
        ?string $emplacement,
        ?string $uniteMesure,
        ?string $terme,
        int $page = 1,
        int $limit = 50
    ): array {
        $qb = $this->createQueryBuilder('s');

        if ($depot) {
            $qb->andWhere('s.depot = :depot')->setParameter('depot', $depot);
        }
        if ($emplacement) {
            $qb->andWhere('s.emplacement = :emplacement')->setParameter('emplacement', $emplacement);
        }
        if ($uniteMesure) {
            $qb->andWhere('s.uniteMesure = :um')->setParameter('um', $uniteMesure);
        }
        if ($terme) {
            $qb->andWhere('s.codeArticle LIKE :t OR s.nom LIKE :t')->setParameter('t', '%' . $terme . '%');
        }

        $total = (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();

        $items = $qb
            ->orderBy('s.nom', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => (int) $total];
    }

    /** @return string[] */
    public function findAllDepots(): array
    {
        return array_column(
            $this->createQueryBuilder('s')
                ->select('DISTINCT s.depot')
                ->orderBy('s.depot', 'ASC')
                ->getQuery()
                ->getArrayResult(),
            'depot'
        );
    }

    /** @return string[] */
    public function findAllEmplacements(): array
    {
        return array_column(
            $this->createQueryBuilder('s')
                ->select('DISTINCT s.emplacement')
                ->where('s.emplacement IS NOT NULL')
                ->orderBy('s.emplacement', 'ASC')
                ->getQuery()
                ->getArrayResult(),
            'emplacement'
        );
    }

    /** @return string[] */
    public function findAllUnites(): array
    {
        return array_column(
            $this->createQueryBuilder('s')
                ->select('DISTINCT s.uniteMesure')
                ->where('s.uniteMesure IS NOT NULL')
                ->orderBy('s.uniteMesure', 'ASC')
                ->getQuery()
                ->getArrayResult(),
            'uniteMesure'
        );
    }
}
