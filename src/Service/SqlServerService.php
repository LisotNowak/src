<?php

namespace App\Service;

use PDO;
use PDOException;

class SqlServerService
{
    private PDO $pdo;

    public function __construct()
    {
        $host = "SRVAPP2";
        $port = 1433;
        $db = "nic";
        $user = "sa";
        $pass = "#NicLatour!";

        // Modifie la chaîne de connexion pour ajouter TrustServerCertificate=YES
        $dsn = "sqlsrv:Server=$host,$port;Database=$db;TrustServerCertificate=YES";

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
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
            throw new \RuntimeException("Erreur SQL : " . $e->getMessage());
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
