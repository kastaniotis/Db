<?php


use Iconic\Db\DatabaseConnection;
use Iconic\Db\Exception\NoResultException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DbTest extends TestCase
{
    private DatabaseConnection $db;

    public function setUp(): void
    {
        $this->db = DatabaseConnection::createSqlite(':memory:', new NullLogger());
        $this->db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->db->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'Alice']);
    }

    public function testGetOneReturnsRow(): void
    {
        $user = $this->db->getOne('SELECT * FROM users WHERE name = :name', ['name' => 'Alice']);
        $this->assertEquals('Alice', $user['name']);
    }

    public function testGetOptionalOneReturnsNullWhenNoResult(): void
                                                                                                                                                                                                                                                                                                                                                                                         {
        $result = $this->db->getOptionalOne('SELECT * FROM users WHERE name = :name', ['name' => 'Bob']);
        $this->assertNull($result);
    }

    public function testGetOneThrowsWhenNoResult(): void
    {
        $this->expectException(NoResultException::class);
        $this->db->getOne('SELECT * FROM users WHERE name = :name', ['name' => 'Bob']);
    }

    public function testExecuteReturnsInsertId(): void
    {
        $id = $this->db->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'Charlie']);
        $this->assertIsInt($id);
        $this->assertGreaterThan(1, $id);
    }

    public function testTransactionRollsBackOnException(): void
    {
        try {
            $this->db->transaction(function(DatabaseConnection $db) {
                $db->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'David']);
                throw new RuntimeException("Something failed");
            });
        } catch (RuntimeException) {}

        $users = $this->db->getMany('SELECT * FROM users WHERE name = :name', ['name' => 'David']);
        $this->assertCount(0, $users);
    }

    public function testScalar(): void
    {
        $result = $this->db->getColumn("SELECT name FROM users WHERE id = :id", ['id' => 1]);
        $this->assertEquals('Alice', $result);
    }

    public function testQuery(): void
    {
        $result = $this->db->query('SELECT * FROM users WHERE name = :name', ['name' => 'Alice']);
        $this->assertEquals('Alice', $result[0]['name']);
    }
}
