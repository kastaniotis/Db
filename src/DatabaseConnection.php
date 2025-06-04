<?php

namespace Iconic\Db;

use Iconic\Db\Exception\DatabaseException;
use Iconic\Db\Exception\NoResultException;
use Iconic\Db\Exception\TooManyResultsException;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class DatabaseConnection {
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger) {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function createMysql(string $host, string $dbname, string $user, string $pass, LoggerInterface $logger): self
    {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        return new self($pdo, $logger);
    }

    public static function createSqlite(string $path, LoggerInterface $logger): self
    {
        $dsn = "sqlite:$path";
        $pdo = new PDO($dsn);
        return new self($pdo, $logger);
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
                $this->bindParameters($statement, $parameters, $types);
                $statement->execute();
            } else {
                $statement->execute($parameters);
            }

            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), [
                'sql' => $sql,
                'parameters' => $parameters
            ]);
            throw new DatabaseException($e);
        }

        $count = count($result);
        if ($count === 0) {
            return null;
        }

        if ($count > 1) {
            $this->logger->error("Too many results for query expecting one", [
                'sql' => $sql,
                'parameters' => $parameters
            ]);
            throw new TooManyResultsException();
        }

        return $result[0];
    }

    public function getMany(string $sql, array $parameters = [], array $types = []): array
    {
        try {
            $statement = $this->pdo->prepare($sql);
            if (!empty($types)) {
                $this->bindParameters($statement, $parameters, $types);
                $statement->execute();
            } else {
                $statement->execute($parameters);
            }

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), [
                'sql' => $sql,
                'parameters' => $parameters
            ]);
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
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function bindParameters(PDOStatement $statement, array $parameters, array $types): void
    {
        foreach ($parameters as $i => $value) {
            $type = $types[$i] ?? PDO::PARAM_STR;
            $statement->bindValue($i + 1, $value, $type);
        }
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
