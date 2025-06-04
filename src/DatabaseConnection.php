<?php

namespace Iconic\Db;

use PDO;

readonly class DatabaseConnection {
    private PDO $pdo;

    public function __construct(string $dsn, string $user, string $pass) {
        $this->pdo = new PDO($dsn, $user, $pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function createMysql(string $host, string $dbname, string $user, string $pass): self
    {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        return new self($pdo);
    }

    public static function createSqlite(string $path): self
    {
        $dsn = "sqlite:$path";
        $pdo = new PDO($dsn);
        return new self($pdo);
    }

    public function query(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        // Simple detection of INSERT
        if (stripos(trim($sql), 'insert') === 0) {
            return (int) $this->pdo->lastInsertId();
        }

        return $statement->rowCount();
    }
}
