<?php

namespace App\Repository\client;

use App\Entity\client\Signataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Signataire>
 *
 * @method Signataire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Signataire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Signataire[]    findAll()
 * @method Signataire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SignataireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signataire::class);
    }

    // Exemple de méthode personnalisée
    // public function findBySignataire(string $nom): array
    // {
    //     return $this->createQueryBuilder('ls')
    //         ->andWhere('ls.signataire LIKE :nom')
    //         ->setParameter('nom', '%' . $nom . '%')
    //         ->getQuery()
    //         ->getResult();
    // }
}
