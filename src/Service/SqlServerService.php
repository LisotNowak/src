<?php

namespace App\Service;

use PDO;
use PDOException;

class SqlServerService
{
    private PDO $pdo;

    public function __construct(
        string $sqlserverHost,
        int $sqlserverPort,
        string $sqlserverDb,
        string $sqlserverUser,
        string $sqlserverPassword,
    ) {
        $dsn = "sqlsrv:Server=$sqlserverHost,$sqlserverPort;Database=$sqlserverDb";

        try {
            $this->pdo = new PDO($dsn, $sqlserverUser, $sqlserverPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('Connexion à la base de données impossible.');
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): void
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erreur SQL : " . $e->getMessage());
            throw new \RuntimeException("Une erreur est survenue lors de l'exécution de la requête.");
        }
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
