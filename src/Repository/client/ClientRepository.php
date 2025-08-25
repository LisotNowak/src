<?php

namespace App\Repository\client;

use App\Entity\client\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.triNom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByVille(string $ville): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.ville = :ville')
            ->setParameter('ville', $ville)
            ->orderBy('c.triNom', 'ASC')
            ->getQuery()
            ->getResult();
    }


}
