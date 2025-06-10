<?php

use Carbon\Carbon;
use Iconic\Db\DatabaseConnection;
use Iconic\Db\Exception\NoResultException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DbTest extends TestCase
{
    private DatabaseConnection $db;
    private string $created;

    public function setUp(): void
    {
        $this->db = DatabaseConnection::connectToSqlite(':memory:', new NullLogger());
        $this->db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->db->execute('CREATE TABLE addresses (id INTEGER PRIMARY KEY, street TEXT, number INTEGER, created DATETIME)');
        $this->db->execute('INSERT INTO users (name) VALUES (:name)', ['name' => 'Alice']);
        $this->created = Carbon::now()->toAtomString();
        $this->db->execute('INSERT INTO addresses (street, number, created) VALUES (:street, :number, :created)',
            [
                'street' => 'Aristotelous',
                'number' => 16,
                'created' => $this->created
            ]);

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

    public function testInsert(): void
    {
        $created = Carbon::now()->toAtomString();
        $result = $this->db->insert('addresses', [
            'street' => 'Dabaki',
            'number' => 9,
            'created' => $created
        ]);

        $this->assertEquals(2, $result);

        $address = $this->db->getOne('SELECT * FROM addresses WHERE id = :id', ['id' => $result]);

        $this->assertEquals('Dabaki', $address['street']);
        $this->assertEquals(9, $address['number']);
        $this->assertEquals($created, $address['created']);
    }

    public function testUpdate(): void
    {
        $address = $this->db->getOne('SELECT * FROM addresses WHERE id = :id', ['id' => 1]);

        $this->assertEquals('Aristotelous', $address['street']);
        $this->assertEquals(16, $address['number']);
        $this->assertEquals($this->created, $address['created']);

        $updated = Carbon::now()->toAtomString();

        $result =  $this->db->update('addresses', [
            'street' => 'Profiti Ilia',
            'number' => 3,
            'created' => $updated,
        ],['id' => 1]);

        $this->assertEquals(1, $result);

        $updatedAddress = $this->db->getOne('SELECT * FROM addresses WHERE id = :id', ['id' => 1]);

        $this->assertEquals('Profiti Ilia', $updatedAddress['street']);
        $this->assertEquals(3, $updatedAddress['number']);
        $this->assertEquals($updated, $updatedAddress['created']);
    }

    public function testDelete()
    {
        $result = $this->db->delete('addresses',['id' => 1]);
        $this->assertEquals(1, $result);

        $count = $this->db->getColumn("SELECT COUNT(*) FROM addresses", []);

        $this->assertEquals(0, $count);
    }

    public function testSelect()
    {
        $result = $this->db->select('users', ['id' => 1]);

        $this->assertEquals($result[0]['name'], 'Alice');
    }
}
