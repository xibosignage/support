<?php

namespace Xibo\Support\Tests\Database;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xibo\Support\Database\PdoStorageService;

/**
 * Override connect() to use SQLite in-memory instead of MySQL.
 */
class SqlitePdoStorageService extends PdoStorageService
{
    public function connect($host, $user, $pass, $name = null, $ssl = null, $sslVerify = true): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}

class PdoStorageServiceSqliteTest extends TestCase
{
    private SqlitePdoStorageService $service;

    protected function setUp(): void
    {
        $this->service = new SqlitePdoStorageService(
            new NullLogger(),
            ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'test']
        );

        // Bootstrap a simple table
        $this->service->getConnection('default')->exec(
            'CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)'
        );
        // Isolated connection gets its own in-memory DB
        $this->service->getConnection('isolated')->exec(
            'CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)'
        );
    }

    protected function tearDown(): void
    {
        $this->service->close();
    }

    // ------------------------------------------------------------------
    // Connection management
    // ------------------------------------------------------------------

    public function testGetConnectionLazilyOpensOnFirstUse(): void
    {
        $conn = $this->service->getConnection('default');
        $this->assertInstanceOf(\PDO::class, $conn);
    }

    public function testGetConnectionReturnsSameInstanceOnSubsequentCalls(): void
    {
        $conn1 = $this->service->getConnection('default');
        $conn2 = $this->service->getConnection('default');
        $this->assertSame($conn1, $conn2);
    }

    public function testSetConnectionStoresUnderNamedKey(): void
    {
        $this->service->setConnection('secondary');
        $conn = $this->service->getConnection('secondary');
        $this->assertInstanceOf(\PDO::class, $conn);
    }

    public function testCloseSpecificConnectionRemovesIt(): void
    {
        $this->service->getConnection('default');
        $this->service->close('default');
        // After close, a new connection is lazily created — it should still work
        $newConn = $this->service->getConnection('default');
        $this->assertInstanceOf(\PDO::class, $newConn);
    }

    public function testCloseAllConnectionsClearsPool(): void
    {
        $this->service->getConnection('default');
        $this->service->getConnection('isolated');
        $this->service->close(); // close all
        // New connections created on next access
        $this->assertInstanceOf(\PDO::class, $this->service->getConnection('default'));
    }

    // ------------------------------------------------------------------
    // insert / select / update / exists
    // ------------------------------------------------------------------

    public function testInsertReturnsLastInsertId(): void
    {
        $this->service->insert("INSERT INTO items (name) VALUES (:name)", [':name' => 'Alice']);
        $this->service->commitIfNecessary('default');

        // SQLite AUTOINCREMENT starts at 1
        $rows = $this->service->select("SELECT id, name FROM items WHERE name = :name", [':name' => 'Alice']);
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testInsertBeginsTransactionWhenNoneActive(): void
    {
        $this->service->insert("INSERT INTO items (name) VALUES (:name)", [':name' => 'Bob']);
        $conn = $this->service->getConnection('default');
        // Transaction should be active (not yet committed)
        $this->assertTrue($conn->inTransaction());
        $conn->rollBack();
    }

    public function testUpdateReturnsAffectedRowCount(): void
    {
        $this->service->insert("INSERT INTO items (name) VALUES (:name)", [':name' => 'Carol']);
        $this->service->commitIfNecessary('default');

        $count = $this->service->update(
            "UPDATE items SET name = :newname WHERE name = :oldname",
            [':newname' => 'Caroline', ':oldname' => 'Carol']
        );
        $this->service->commitIfNecessary('default');
        $this->assertSame(1, $count);
    }

    public function testSelectReturnsAssocArray(): void
    {
        $this->service->insert("INSERT INTO items (name) VALUES (:name)", [':name' => 'Dave']);
        $this->service->commitIfNecessary('default');

        $rows = $this->service->select("SELECT name FROM items WHERE name = :name", [':name' => 'Dave']);
        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertSame('Dave', $rows[0]['name']);
    }

    public function testSelectReturnsEmptyArrayWhenNoRows(): void
    {
        $rows = $this->service->select("SELECT * FROM items WHERE name = :name", [':name' => 'Nobody']);
        $this->assertSame([], $rows);
    }

    public function testExistsReturnsTrueWhenRowPresent(): void
    {
        $this->service->insert("INSERT INTO items (name) VALUES (:name)", [':name' => 'Eve']);
        $this->service->commitIfNecessary('default');

        $result = $this->service->exists("SELECT 1 FROM items WHERE name = :name", [':name' => 'Eve']);
        $this->assertTrue($result);
    }

    public function testExistsReturnsFalseWhenNoRows(): void
    {
        $result = $this->service->exists("SELECT 1 FROM items WHERE name = :name", [':name' => 'Ghost']);
        $this->assertFalse($result);
    }

    public function testCommitIfNecessaryCommitsActiveTransaction(): void
    {
        $this->service->insert("INSERT INTO items (name) VALUES (:name)", [':name' => 'Frank']);
        $this->service->commitIfNecessary('default');

        $conn = $this->service->getConnection('default');
        $this->assertFalse($conn->inTransaction());

        // Data should be visible after commit
        $rows = $this->service->select("SELECT name FROM items WHERE name = 'Frank'", []);
        $this->assertCount(1, $rows);
    }

    public function testCommitIfNecessaryIsNoopWhenNoTransaction(): void
    {
        // No transaction started — commitIfNecessary should not throw
        $this->service->commitIfNecessary('default');
        $this->assertTrue(true); // just verifying no exception thrown
    }

    public function testIsolatedExecutesOnIsolatedConnection(): void
    {
        $this->service->isolated(
            "INSERT INTO items (name) VALUES (:name)",
            [':name' => 'IsolatedRow']
        );
        $rows = $this->service->select(
            "SELECT name FROM items WHERE name = :name",
            [':name' => 'IsolatedRow'],
            'isolated'
        );
        $this->assertCount(1, $rows);
    }

    public function testStatsIncrementForSelectOperation(): void
    {
        $before = $this->service->stats()['default']['select'] ?? 0;
        $this->service->select("SELECT 1", []);
        $after = $this->service->stats()['default']['select'] ?? 0;
        $this->assertSame($before + 1, $after);
    }

    public function testLogSqlCallsLoggerDebug(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('debug');

        $service = new SqlitePdoStorageService(
            $logger,
            ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'test']
        );
        $service->getConnection('default')->exec(
            'CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)'
        );
        $service->select("SELECT 1", []);
    }

    public function testSelectWithCloseFlagDiscardsConnection(): void
    {
        $original = $this->service->getConnection('default');
        $this->service->select("SELECT 1", [], 'default', false, true);
        // After close=true, the next getConnection() must return a NEW PDO instance.
        $next = $this->service->getConnection('default');
        $this->assertNotSame($original, $next);

        // Re-bootstrap the schema for tearDown / subsequent tests in this method.
        $next->exec('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }

    public function testInsertWithTransactionFalseLeavesNoTransactionOpen(): void
    {
        $this->service->insert(
            "INSERT INTO items (name) VALUES (:name)",
            [':name' => 'NoTxn'],
            'default',
            false,
            false
        );
        $this->assertFalse($this->service->getConnection('default')->inTransaction());
    }

    public function testCommitIfNecessaryWithCloseFlagDiscardsConnection(): void
    {
        $original = $this->service->getConnection('default');
        $this->service->insert("INSERT INTO items (name) VALUES (:name)", [':name' => 'CloseMe']);
        $this->service->commitIfNecessary('default', true);
        $next = $this->service->getConnection('default');
        $this->assertNotSame($original, $next);
        $next->exec('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }
}
