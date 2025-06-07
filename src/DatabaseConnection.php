<?php

namespace Iconic\Db;

use Iconic\Db\Exception\DatabaseException;
use Iconic\Db\Exception\NoResultException;
use Iconic\Db\Exception\TooManyResultsException;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class DatabaseConnection
{
    private PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(PDO $pdo, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function connectToMysqlHost(string $host, string $dbname, string $user, string $pass, LoggerInterface $logger): self
    {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        return new self($pdo, $logger);
    }

    public static function connectToMysqlSocket(string $socket, string $dbname, string $user, string $pass, LoggerInterface $logger): self
    {
        $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        return new self($pdo, $logger);
    }

    public static function connectToSqlite(string $path, LoggerInterface $logger): self
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
            return (int)$this->pdo->lastInsertId();
        }

        return $statement->rowCount();
    }

    /**
     * @throws NoResultException
     */
    public function getOne(string $sql, array $parameters = []): array
    {
        $result = $this->runFetchOne($sql, $parameters);
        if ($result === null) {
            throw new NoResultException();
        }
        return $result;
    }

    public function getOptionalOne(string $sql, array $parameters = []): ?array
    {
        return $this->runFetchOne($sql, $parameters);
    }

    private function runFetchOne(string $sql, array $parameters): ?array
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);

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

    public function getMany(string $sql, array $parameters = []): array
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->logger->error("Database error: " . $e->getMessage(), [
                'sql' => $sql,
                'parameters' => $parameters
            ]);
            throw new DatabaseException($e);
        }
    }

    public function getColumn(string $sql, array $parameters = []): mixed
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

    /**
     * @throws Throwable
     */
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

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
