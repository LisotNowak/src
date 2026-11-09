<?php

namespace App\Repository\tracabilite;

use App\Entity\tracabilite\Tache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tache::class);
    }

    /** Toutes les tâches triées par nom, avec leur type et les équipes de ce type */
    public function findAllSorted(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.type', 'ty')->addSelect('ty')
            ->leftJoin('ty.equipes', 'e')->addSelect('e')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Uniquement les tâches terrain (avec parcelle) */
    public function findTachesTerrain(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.sansParcel = false')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Uniquement les tâches RH/administratives (sans parcelle) */
    public function findTachesRH(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.sansParcel = true')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByNom(string $nom): ?Tache
    {
        return $this->findOneBy(['nom' => $nom]);
    }

    /**
     * Types de ces tâches, triés par nom, avec pour chacun ses tâches parmi celles données.
     *
     * @param string[] $noms
     * @return array<int, array{nom: string, taches: string[]}> indexé par id du type
     */
    public function findTypesDesTaches(array $noms): array
    {
        if ($noms === []) {
            return [];
        }

        $lignes = $this->createQueryBuilder('t')
            ->select('ty.id AS typeId', 'ty.nom AS typeNom', 't.nom AS tacheNom')
            ->innerJoin('t.type', 'ty')
            ->where('t.nom IN (:noms)')
            ->setParameter('noms', $noms)
            ->orderBy('ty.nom', 'ASC')
            ->addOrderBy('t.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $types = [];
        foreach ($lignes as $l) {
            $types[$l['typeId']]['nom'] = $l['typeNom'];
            $types[$l['typeId']]['taches'][] = $l['tacheNom'];
        }
        return $types;
    }
}
