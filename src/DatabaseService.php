<?php

namespace Iconic\Db;

use Exception;
use Iconic\Db\Exception\DatabaseException;
use Iconic\Db\Exception\NoResultException;
use Iconic\Db\Exception\TooManyResultsException;
use Psr\Log\LoggerInterface;

readonly class DatabaseService {
    public function __construct(
        private LoggerInterface $logger,
        private DatabaseConnection $pdo
    )
    {
    }

    /**
     * @param string $sql
     * @param list<mixed> $parameters
     * @param list<mixed> $types
     * @return array<string, mixed>
     * @throws NoResultException
     */
    public function getOne(string $sql, array $parameters = [], array $types = []): array
    {
        try {
            //$result = $this->connection->fetchAllAssociative($sql, $parameters, $types);
            $result = $this->pdo->query($sql, $parameters);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new DatabaseException($e);
        }

        $count = count($result);
        if($count === 0){
            $this->logger->error(NoResultException::MESSAGE);
            throw new NoResultException();
        }
        elseif($count > 1){
            throw new TooManyResultsException();
        }

        return $result[0];
    }
}
