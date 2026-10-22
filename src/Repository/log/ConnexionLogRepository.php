<?php

namespace App\Repository\log;

use App\Entity\log\ConnexionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Les écritures passent par DBAL (et non persist/flush) pour ne jamais
 * flusher par effet de bord les entités en cours de modification ailleurs.
 */
class ConnexionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConnexionLog::class);
    }

    public function enregistrer(array $data): void
    {
        $conn = $this->getEntityManager()->getConnection();
        // La séquence créée par le mapping Doctrine n'est pas en DEFAULT de la colonne
        $data['id'] = $conn->fetchOne("SELECT nextval('app_log_connexion_id_seq')");
        $data['cree_le'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $conn->insert('app_log_connexion', $data);
    }

    /** Journal des connexions sur la période, du plus récent au plus ancien */
    public function findJournal(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email, int $limit = 300): array
    {
        [$where, $params] = $this->filtre($du, $au, $email);

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT * FROM app_log_connexion WHERE $where ORDER BY cree_le DESC LIMIT $limit",
            $params
        );
    }

    /** Compteurs : connexions réussies, échecs, utilisateurs distincts */
    public function stats(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email): array
    {
        [$where, $params] = $this->filtre($du, $au, $email);

        return $this->getEntityManager()->getConnection()->fetchAssociative(
            "SELECT
                COUNT(*) FILTER (WHERE evenement = 'connexion') AS connexions,
                COUNT(*) FILTER (WHERE evenement = 'echec') AS echecs,
                COUNT(DISTINCT email) FILTER (WHERE evenement = 'connexion') AS utilisateurs
             FROM app_log_connexion WHERE $where",
            $params
        );
    }

    /** Couples email/IP ayant échoué plusieurs fois : tentatives suspectes ou utilisateurs bloqués */
    public function findEchecsRepetes(\DateTimeImmutable $du, \DateTimeImmutable $au, int $seuil = 3): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT email, ip, COUNT(*) AS nb, MAX(cree_le) AS dernier
             FROM app_log_connexion
             WHERE evenement = 'echec' AND cree_le BETWEEN :du AND :au
             GROUP BY email, ip
             HAVING COUNT(*) >= :seuil
             ORDER BY nb DESC",
            ['du' => $du->format('Y-m-d H:i:s'), 'au' => $au->format('Y-m-d H:i:s'), 'seuil' => $seuil]
        );
    }

    public function purger(\DateTimeImmutable $avant): int
    {
        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM app_log_connexion WHERE cree_le < :avant',
            ['avant' => $avant->format('Y-m-d H:i:s')]
        );
    }

    private function filtre(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email): array
    {
        $where = 'cree_le BETWEEN :du AND :au';
        $params = ['du' => $du->format('Y-m-d H:i:s'), 'au' => $au->format('Y-m-d H:i:s')];
        if ($email) {
            $where .= ' AND email ILIKE :email';
            $params['email'] = '%'.$email.'%';
        }

        return [$where, $params];
    }
}
