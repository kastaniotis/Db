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

    public function getOne(string $sql, array $parameters = [], array $types = []): array
    {
        $result = $this->runFetchOne($sql, $parameters, $types);
    
        if ($result === null) {
            throw new NoResultException();
        }
    
        return $result;
    }

    public function getOptionalOne(string $sql, array $parameters = [], array $types = []): ?array
    {
        return $this->runFetchOne($sql, $parameters, $types);
    }

    private function runFetchOne(string $sql, array $parameters, array $types): ?array
    {
        try {
            $statement = $this->pdo->prepare($sql);
    
            if (!empty($types)) {
                foreach ($parameters as $i => $value) {
                    $type = $types[$i] ?? PDO::PARAM_STR;
                    $statement->bindValue($i + 1, $value, $type);
                }
                $statement->execute();
            } else {
                $statement->execute($parameters);
            }
    
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), ['sql' => $sql, 'parameters' => $parameters]);
            throw new DatabaseException($e);
        }
    
        $count = count($result);
        if ($count === 0) {
            return null;
        }
    
        if ($count > 1) {
            $this->logger->error("Too many results for query expecting one", ['sql' => $sql, 'parameters' => $parameters]);
            throw new TooManyResultsException();
        }
    
        return $result[0];
    }

    public function getMany(string $sql, array $parameters = [], array $types = []): array
    {
        try {
            $statement = $this->pdo->prepare($sql);
    
            if (!empty($types)) {
                foreach ($parameters as $i => $value) {
                    $type = $types[$i] ?? PDO::PARAM_STR;
                    $statement->bindValue($i + 1, $value, $type);
                }
                $statement->execute();
            } else {
                $statement->execute($parameters);
            }
    
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), [
                'sql' => $sql,
                'parameters' => $parameters
            ]);
            throw new DatabaseException($e);
        }
    }

    public function getScalar(string $sql, array $parameters = []): mixed
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parameters);
            return $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), ['sql' => $sql]);
            throw new DatabaseException($e);
        }
    }

    public function transaction(callable $fn): mixed
    {
        try {
            $this->pdo->beginTransaction();
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
