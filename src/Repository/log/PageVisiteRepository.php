<?php

namespace App\Repository\log;

use App\Entity\log\PageVisite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Les écritures passent par DBAL (et non persist/flush) pour ne jamais
 * flusher par effet de bord les entités en cours de modification ailleurs.
 */
class PageVisiteRepository extends ServiceEntityRepository
{
    /** Plafond du temps actif accepté pour une page (8 h) */
    private const TEMPS_MAX_S = 28800;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageVisite::class);
    }

    public function enregistrer(array $data): void
    {
        $data['visite_le'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->getEntityManager()->getConnection()->insert('app_log_page', $data);
    }

    /** Met à jour le temps actif, uniquement pour une visite appartenant à l'utilisateur */
    public function majTempsActif(string $id, int $userId, int $secondes): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE app_log_page SET temps_actif_s = GREATEST(COALESCE(temps_actif_s, 0), :s)
             WHERE id = :id AND user_id = :uid',
            ['s' => min(max($secondes, 0), self::TEMPS_MAX_S), 'id' => $id, 'uid' => $userId]
        );
    }

    public function stats(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email): array
    {
        [$where, $params] = $this->filtre($du, $au, $email);

        return $this->getEntityManager()->getConnection()->fetchAssociative(
            "SELECT
                COUNT(*) FILTER (WHERE methode_http = 'GET') AS pages,
                COALESCE(SUM(temps_actif_s), 0) AS temps_total,
                COUNT(*) FILTER (WHERE statut >= 500) AS erreurs_serveur
             FROM app_log_page WHERE $where",
            $params
        );
    }

    /** Activité cumulée par utilisateur */
    public function parUtilisateur(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email): array
    {
        [$where, $params] = $this->filtre($du, $au, $email);

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT email,
                COUNT(*) FILTER (WHERE methode_http = 'GET') AS pages,
                COUNT(*) FILTER (WHERE methode_http <> 'GET') AS actions,
                COUNT(DISTINCT session) AS sessions,
                COUNT(DISTINCT visite_le::date) AS jours,
                COALESCE(SUM(temps_actif_s), 0) AS temps_total,
                MIN(visite_le) AS premiere,
                MAX(visite_le) AS derniere
             FROM app_log_page WHERE $where
             GROUP BY email
             ORDER BY temps_total DESC",
            $params
        );
    }

    /** Statistiques par page (route) : fréquentation, temps passé, performance, erreurs */
    public function parPage(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email): array
    {
        [$where, $params] = $this->filtre($du, $au, $email);

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT route,
                MIN(chemin) AS exemple,
                COUNT(*) AS vues,
                COUNT(DISTINCT email) AS utilisateurs,
                COALESCE(SUM(temps_actif_s), 0) AS temps_total,
                ROUND(AVG(temps_actif_s)) AS temps_moyen,
                ROUND(AVG(duree_serveur_ms)) AS serveur_moyen,
                MAX(duree_serveur_ms) AS serveur_max,
                COUNT(*) FILTER (WHERE statut >= 400) AS erreurs
             FROM app_log_page WHERE $where AND methode_http = 'GET'
             GROUP BY route
             ORDER BY vues DESC",
            $params
        );
    }

    /** Dernières pages vues / actions */
    public function findJournal(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email, int $limit = 300): array
    {
        [$where, $params] = $this->filtre($du, $au, $email);

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT * FROM app_log_page WHERE $where ORDER BY visite_le DESC LIMIT $limit",
            $params
        );
    }

    public function purger(\DateTimeImmutable $avant): int
    {
        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM app_log_page WHERE visite_le < :avant',
            ['avant' => $avant->format('Y-m-d H:i:s')]
        );
    }

    private function filtre(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $email): array
    {
        $where = 'visite_le BETWEEN :du AND :au';
        $params = ['du' => $du->format('Y-m-d H:i:s'), 'au' => $au->format('Y-m-d H:i:s')];
        if ($email) {
            $where .= ' AND email ILIKE :email';
            $params['email'] = '%'.$email.'%';
        }

        return [$where, $params];
    }
}
