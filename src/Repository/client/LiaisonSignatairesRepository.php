<?php

namespace App\Repository\client;

use App\Entity\client\LiaisonSignataires;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiaisonSignataires>
 *
 * @method LiaisonSignataires|null find($id, $lockMode = null, $lockVersion = null)
 * @method LiaisonSignataires|null findOneBy(array $criteria, array $orderBy = null)
 * @method LiaisonSignataires[]    findAll()
 * @method LiaisonSignataires[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LiaisonSignatairesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiaisonSignataires::class);
    }

    // Exemple de méthode personnalisée
    public function findBySignataire(string $nom): array
    {
        return $this->createQueryBuilder('ls')
            ->andWhere('ls.signataire LIKE :nom')
            ->setParameter('nom', '%' . $nom . '%')
            ->getQuery()
            ->getResult();
    }
}
