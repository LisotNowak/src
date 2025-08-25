<?php

namespace App\Repository\client;

use App\Entity\client\AssociationSignataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssociationSignataire>
 *
 * @method AssociationSignataire|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssociationSignataire|null findOneBy(array $criteria, array $orderBy = null)
 * @method AssociationSignataire[]    findAll()
 * @method AssociationSignataire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AssociationSignataireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssociationSignataire::class);
    }

    // Exemple de méthode personnalisée
    // public function findByAssociationSignataire(string $nom): array
    // {
    //     return $this->createQueryBuilder('ls')
    //         ->andWhere('ls.AssociationSignataire LIKE :nom')
    //         ->setParameter('nom', '%' . $nom . '%')
    //         ->getQuery()
    //         ->getResult();
    // }
}
