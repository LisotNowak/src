<?php

namespace App\Service\Inventaire;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportStockService
{
    private const BATCH_SIZE = 500;

    private Connection $conn;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->conn = $em->getConnection();
    }

    /**
     * @param bool $truncate  true = vider le stock avant import (rapide)
     *                        false = mise à jour intelligente via table temporaire
     *
     * @return array{inserted: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file, bool $truncate = true): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // ── 1. Lecture Excel en mode données uniquement (rapide) ──────────
        $reader = IOFactory::createReaderForFile($file->getPathname());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getPathname());
        $sheet       = $spreadsheet->getActiveSheet();
        $highestRow  = $sheet->getHighestRow();

        $rows    = [];
        $skipped = 0;
        $errors  = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            try {
                $depot = trim((string) ($sheet->getCell('A' . $r)->getValue() ?? ''));
                $code  = trim((string) ($sheet->getCell('B' . $r)->getValue() ?? ''));
                $nom   = trim((string) ($sheet->getCell('C' . $r)->getValue() ?? ''));

                if (!$depot || !$code || !$nom) {
                    $skipped++;
                    continue;
                }

                $rows[] = [
                    'depot'               => $depot,
                    'code_article'        => $code,
                    'nom'                 => $nom,
                    'numero_lot'          => $this->toBigint($sheet->getCell('D' . $r)->getValue()),
                    'emplacement'         => trim((string) ($sheet->getCell('E' . $r)->getValue() ?? '')) ?: null,
                    'stock_disponible'    => $this->toDecimal($sheet->getCell('F' . $r)->getValue()),
                    'quantite_affectee'   => $this->toDecimal($sheet->getCell('G' . $r)->getValue()),
                    'stock_affecte'       => $this->toDecimal($sheet->getCell('H' . $r)->getValue()),
                    'unite_mesure'        => trim((string) ($sheet->getCell('I' . $r)->getValue() ?? '')) ?: null,
                    'affecte'             => $this->toBool($sheet->getCell('J' . $r)->getValue()),
                    'statut'              => $this->toInt($sheet->getCell('K' . $r)->getValue()),
                    'attribut_principal1' => trim((string) ($sheet->getCell('L' . $r)->getValue() ?? '')) ?: null,
                    'attribut_principal2' => trim((string) ($sheet->getCell('M' . $r)->getValue() ?? '')) ?: null,
                ];
            } catch (\Throwable $e) {
                $errors[$r] = $e->getMessage();
            }
        }

        // Libère la mémoire du spreadsheet immédiatement
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($rows)) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
        }

        $this->conn->beginTransaction();

        try {
            // ── 2. Table temporaire de staging ────────────────────────────
            // Sans ON COMMIT DROP : la transaction gère le cycle de vie
            $this->conn->executeStatement('DROP TABLE IF EXISTS tmp_stock_import');
            $this->conn->executeStatement('
                CREATE TEMP TABLE tmp_stock_import (
                    depot               VARCHAR(20)     NOT NULL,
                    code_article        VARCHAR(50)     NOT NULL,
                    nom                 VARCHAR(255)    NOT NULL,
                    numero_lot          BIGINT          DEFAULT NULL,
                    emplacement         VARCHAR(50)     DEFAULT NULL,
                    stock_disponible    NUMERIC(12,4)   DEFAULT NULL,
                    quantite_affectee   NUMERIC(12,4)   DEFAULT NULL,
                    stock_affecte       NUMERIC(12,4)   DEFAULT NULL,
                    unite_mesure        VARCHAR(10)     DEFAULT NULL,
                    affecte             BOOLEAN         DEFAULT NULL,
                    statut              INTEGER         DEFAULT NULL,
                    attribut_principal1 VARCHAR(100)    DEFAULT NULL,
                    attribut_principal2 VARCHAR(100)    DEFAULT NULL
                )
            ');

            // ── 3. Insertion dans la table temporaire par lots ────────────
            foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
                $placeholders = [];
                $params       = [];

                foreach ($batch as $row) {
                    $placeholders[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?)';
                    array_push($params,
                        $row['depot'],
                        $row['code_article'],
                        $row['nom'],
                        $row['numero_lot'],
                        $row['emplacement'],
                        $row['stock_disponible'],
                        $row['quantite_affectee'],
                        $row['stock_affecte'],
                        $row['unite_mesure'],
                        $row['affecte'],
                        $row['statut'],
                        $row['attribut_principal1'],
                        $row['attribut_principal2'],
                    );
                }

                $this->conn->executeStatement(
                    'INSERT INTO tmp_stock_import
                        (depot,code_article,nom,numero_lot,emplacement,
                         stock_disponible,quantite_affectee,stock_affecte,
                         unite_mesure,affecte,statut,attribut_principal1,attribut_principal2)
                     VALUES ' . implode(',', $placeholders),
                    $params
                );
            }

            // ── 4a. Mode REMPLACEMENT COMPLET (TRUNCATE + INSERT) ─────────
            if ($truncate) {
                $this->conn->executeStatement(
                    'TRUNCATE TABLE inventaire.stock_article RESTART IDENTITY'
                );

                $this->conn->executeStatement('
                    INSERT INTO inventaire.stock_article
                        (depot,code_article,nom,numero_lot,emplacement,
                         stock_disponible,quantite_affectee,stock_affecte,
                         unite_mesure,affecte,statut,attribut_principal1,attribut_principal2)
                    SELECT
                        depot,code_article,nom,numero_lot,emplacement,
                        stock_disponible,quantite_affectee,stock_affecte,
                        unite_mesure,affecte,statut,attribut_principal1,attribut_principal2
                    FROM tmp_stock_import
                ');

                $this->conn->commit();

                return ['inserted' => count($rows), 'updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
            }

            // ── 4b. Mode MISE À JOUR (DELETE matching + INSERT) ───────────
            $deletedCount = (int) $this->conn->fetchOne('
                SELECT COUNT(*) FROM inventaire.stock_article sa
                WHERE EXISTS (
                    SELECT 1 FROM tmp_stock_import t
                    WHERE t.code_article = sa.code_article
                      AND (t.numero_lot  IS NOT DISTINCT FROM sa.numero_lot)
                      AND (t.emplacement IS NOT DISTINCT FROM sa.emplacement)
                )
            ');

            $this->conn->executeStatement('
                DELETE FROM inventaire.stock_article sa
                WHERE EXISTS (
                    SELECT 1 FROM tmp_stock_import t
                    WHERE t.code_article = sa.code_article
                      AND (t.numero_lot  IS NOT DISTINCT FROM sa.numero_lot)
                      AND (t.emplacement IS NOT DISTINCT FROM sa.emplacement)
                )
            ');

            $this->conn->executeStatement('
                INSERT INTO inventaire.stock_article
                    (depot,code_article,nom,numero_lot,emplacement,
                     stock_disponible,quantite_affectee,stock_affecte,
                     unite_mesure,affecte,statut,attribut_principal1,attribut_principal2)
                SELECT
                    depot,code_article,nom,numero_lot,emplacement,
                    stock_disponible,quantite_affectee,stock_affecte,
                    unite_mesure,affecte,statut,attribut_principal1,attribut_principal2
                FROM tmp_stock_import
            ');

            $this->conn->commit();

            return [
                'inserted' => count($rows) - $deletedCount,
                'updated'  => $deletedCount,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ];

        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ── Helpers de conversion ──────────────────────────────────────────────

    private function toDecimal(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }
        return (string) (float) $val;
    }

    private function toBigint(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }
        return (string) (int) (float) $val;
    }

    private function toBool(mixed $val): ?bool
    {
        if ($val === null || $val === '') {
            return null;
        }
        return (float) $val === 1.0;
    }

    private function toInt(mixed $val): ?int
    {
        if ($val === null || $val === '') {
            return null;
        }
        return (int) (float) $val;
    }
}
