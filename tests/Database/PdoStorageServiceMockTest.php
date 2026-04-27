<?php

namespace Xibo\Support\Tests\Database;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Xibo\Support\Database\PdoStorageService;
use Xibo\Support\Exception\DeadLockException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Allows injecting specific PDO instances per connection name.
 */
class InjectablePdoStorageService extends PdoStorageService
{
    /** @var array<string, \PDO> */
    public array $injectedConnections = [];

    public function connect($host, $user, $pass, $name = null, $ssl = null, $sslVerify = true): \PDO
    {
        // Pop the next injected connection for any name requested
        if (!empty($this->injectedConnections)) {
            return array_shift($this->injectedConnections);
        }
        throw new \RuntimeException('No injected connection available');
    }
}

class PdoStorageServiceMockTest extends TestCase
{
    private function config(): array
    {
        return ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'name' => 'test'];
    }

    private function service(?\Psr\Log\LoggerInterface $logger = null): InjectablePdoStorageService
    {
        return new InjectablePdoStorageService($logger ?? new NullLogger(), $this->config());
    }

    private function pdoException(int $code): \PDOException
    {
        $e = new \PDOException('Database error', $code);
        $e->errorInfo = ['HY000', $code, 'Database error'];
        return $e;
    }

    private function mockPdoThatSucceeds(mixed $fetchResult = null): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($fetchResult ?? ['id' => 1]);
        $stmt->method('fetchAll')->willReturn($fetchResult !== null ? [$fetchResult] : [['id' => 1]]);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('lastInsertId')->willReturn('42');
        return $pdo;
    }

    private function mockPdoThatThrowsOnExecute(\PDOException $exception): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willThrowException($exception);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(true);
        return $pdo;
    }

    // ------------------------------------------------------------------
    // exists — reconnect on 2006
    // ------------------------------------------------------------------

    public function testExistsRethrowsNon2006Exception(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(1045));

        $this->expectException(\PDOException::class);
        $svc->exists('SELECT 1', []);
    }

    public function testExistsRethrowsWhenReconnectFalseAndExceptionIsAny(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(2006));

        $this->expectException(\PDOException::class);
        // reconnect=false → should rethrow immediately
        $svc->exists('SELECT 1', [], 'default', false);
    }

    public function testExistsReconnectsOn2006AndRetries(): void
    {
        $svc = $this->service();
        // First connection throws 2006, second succeeds
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(2006));
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $result = $svc->exists('SELECT 1', [], 'default', true);
        $this->assertTrue($result);
    }

    // ------------------------------------------------------------------
    // insert — reconnect on 2006
    // ------------------------------------------------------------------

    public function testInsertReconnectsOn2006(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(2006));
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $id = $svc->insert('INSERT INTO t (x) VALUES (:x)', [':x' => 1], 'default', true);
        $this->assertSame(42, $id);
    }

    // ------------------------------------------------------------------
    // update — reconnect on 2006
    // ------------------------------------------------------------------

    public function testUpdateReconnectsOn2006(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(2006));
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $rows = $svc->update('UPDATE t SET x = :x', [':x' => 1], 'default', true);
        $this->assertSame(1, $rows);
    }

    // ------------------------------------------------------------------
    // select — reconnect on 2006
    // ------------------------------------------------------------------

    public function testSelectReconnectsOn2006(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(2006));
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $rows = $svc->select('SELECT * FROM t', [], 'default', true);
        $this->assertIsArray($rows);
    }

    // ------------------------------------------------------------------
    // isolated — reconnect on 2006
    // ------------------------------------------------------------------

    public function testIsolatedReconnectsOn2006(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(2006));
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $svc->isolated('INSERT INTO t (x) VALUES (:x)', [':x' => 1], 'isolated', true);
        $this->assertTrue(true); // no exception = success
    }

    // ------------------------------------------------------------------
    // updateWithDeadlockLoop
    // ------------------------------------------------------------------

    public function testDeadlockLoopSucceedsOnFirstTry(): void
    {
        $svc = $this->service(); // NullLogger — logSql also calls debug(), so we don't assert on it
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $svc->updateWithDeadlockLoop('UPDATE t SET x = 1', []);
        $this->assertTrue(true); // no exception = success
    }

    public function testDeadlockLoopRetriesOn1213ThenSucceeds(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($this->pdoException(1213)),
                true
            );

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->updateWithDeadlockLoop('UPDATE t SET x = 1', []);
        $this->assertTrue(true);
    }

    public function testDeadlockLoopRetriesOn1205ThenSucceeds(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($this->pdoException(1205)),
                true
            );

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->updateWithDeadlockLoop('UPDATE t SET x = 1', []);
        $this->assertTrue(true);
    }

    public function testDeadlockLoopRethrowsNon1213Or1205Immediately(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(1045));

        $this->expectException(\PDOException::class);
        $svc->updateWithDeadlockLoop('UPDATE t SET x = 1', []);
    }

    public function testDeadlockLoopThrowsDeadLockExceptionAfterMaxRetries(): void
    {
        // Fails all 3 attempts (initial + 2 retries)
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willThrowException($this->pdoException(1213));

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $this->expectException(DeadLockException::class);
        $this->expectExceptionMessageMatches('/after 2 retries/');
        $svc->updateWithDeadlockLoop('UPDATE t SET x = 1', []);
    }

    // ------------------------------------------------------------------
    // getVersion
    // ------------------------------------------------------------------

    public function testGetVersionParsesVersionString(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([['v' => '8.0.32-MariaDB']]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        // Reset static version cache via reflection
        $ref = new \ReflectionClass(PdoStorageService::class);
        $prop = $ref->getProperty('_version');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $version = $svc->getVersion();
        $this->assertSame('8.0.32', $version);
    }

    public function testGetVersionReturnsNullWhenNoRows(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $ref = new \ReflectionClass(PdoStorageService::class);
        $prop = $ref->getProperty('_version');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $this->assertNull($svc->getVersion());
    }

    // ------------------------------------------------------------------
    // setTimeZone
    // ------------------------------------------------------------------

    public function testSetTimeZoneExecutesSetQuery(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with("SET time_zone = 'UTC';");

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->setTimeZone('UTC');
    }

    public function testSetTimeZoneAcceptsNumericOffset(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with("SET time_zone = '-08:00';");

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->setTimeZone('-08:00');
    }

    public function testSetTimeZoneAcceptsIanaName(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with("SET time_zone = 'Europe/London';");

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->setTimeZone('Europe/London');
    }

    /** @dataProvider timeZoneInjectionPayloadProvider */
    public function testSetTimeZoneRejectsMaliciousInput(string $payload): void
    {
        $svc = $this->service();
        // No injectedConnections — if validation passes, openConnection() will throw.

        $this->expectException(InvalidArgumentException::class);
        $svc->setTimeZone($payload);
    }

    public static function timeZoneInjectionPayloadProvider(): array
    {
        return [
            ["UTC'; DROP TABLE users; --"],
            ['UTC; DROP'],
            ['UTC OR 1=1'],
            ["UTC' --"],
            [''],
            [' '],
            ['UTC UTC'],
            ['UTC\\'],
            ['/etc/passwd'],
            ['1UTC'],
        ];
    }

    // ------------------------------------------------------------------
    // ErrorException reconnect path
    // ------------------------------------------------------------------

    /**
     * Helper: a PDO whose statement throws \ErrorException on execute.
     */
    private function mockPdoThatThrowsErrorExceptionOnExecute(): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willThrowException(
            new \ErrorException('Error while sending QUERY packet.')
        );

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('beginTransaction')->willReturn(true);
        return $pdo;
    }

    public function testSelectReconnectsOnErrorException(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsErrorExceptionOnExecute();
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $rows = $svc->select('SELECT * FROM t', [], 'default', true);
        $this->assertIsArray($rows);
    }

    public function testInsertReconnectsOnErrorException(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsErrorExceptionOnExecute();
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $id = $svc->insert('INSERT INTO t (x) VALUES (:x)', [':x' => 1], 'default', true);
        $this->assertSame(42, $id);
    }

    public function testUpdateReconnectsOnErrorException(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsErrorExceptionOnExecute();
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $rows = $svc->update('UPDATE t SET x = :x', [':x' => 1], 'default', true);
        $this->assertSame(1, $rows);
    }

    public function testExistsReconnectsOnErrorException(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsErrorExceptionOnExecute();
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $result = $svc->exists('SELECT 1', [], 'default', true);
        $this->assertTrue($result);
    }

    public function testIsolatedReconnectsOnErrorException(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsErrorExceptionOnExecute();
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $svc->isolated('INSERT INTO t (x) VALUES (:x)', [':x' => 1], 'isolated', true);
        $this->assertTrue(true); // no exception = success
    }

    public function testSelectRethrowsErrorExceptionWhenReconnectFalse(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatThrowsErrorExceptionOnExecute();

        $this->expectException(\ErrorException::class);
        $svc->select('SELECT 1', [], 'default', false);
    }

    // ------------------------------------------------------------------
    // $transaction flag
    // ------------------------------------------------------------------

    public function testInsertWithTransactionFalseSkipsBeginTransaction(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('1');
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('inTransaction');

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->insert('INSERT INTO t (x) VALUES (:x)', [':x' => 1], 'default', false, false);
    }

    public function testUpdateWithTransactionFalseSkipsBeginTransaction(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('inTransaction');

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->update('UPDATE t SET x = :x', [':x' => 1], 'default', false, false);
    }

    public function testDeadlockLoopWithTransactionFalseSkipsBeginTransaction(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('inTransaction');

        $svc = $this->service();
        $svc->injectedConnections[] = $pdo;

        $svc->updateWithDeadlockLoop('UPDATE t SET x = 1', [], 'default', false);
    }

    // ------------------------------------------------------------------
    // $close flag
    // ------------------------------------------------------------------

    public function testSelectWithCloseFlagClosesConnectionAfterQuery(): void
    {
        $svc = $this->service();
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $svc->select('SELECT 1', [], 'default', false, true);

        // After close, getConnection() must request a new connection — and we
        // queue another mock so it succeeds.
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();
        $svc->select('SELECT 2', [], 'default', false, false);
        $this->assertEmpty($svc->injectedConnections, 'Both injected connections should have been consumed');
    }

    // ------------------------------------------------------------------
    // SQL syntax-error logging (1064)
    // ------------------------------------------------------------------

    public function testSelectLogsSqlAtErrorLevelOnSyntaxError(): void
    {
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('error');

        $svc = $this->service($logger);
        $svc->injectedConnections[] = $this->mockPdoThatThrowsOnExecute($this->pdoException(1064));

        try {
            $svc->select('NOT VALID SQL', []);
            $this->fail('Expected PDOException');
        } catch (\PDOException) {
            // expected
        }
    }

    // ------------------------------------------------------------------
    // Parameter redaction in logSql
    // ------------------------------------------------------------------

    public function testLogSqlDoesNotLeakParameterValues(): void
    {
        $captured = [];
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->method('debug')->willReturnCallback(
            function ($message, $context = []) use (&$captured): void {
                $captured[] = ['message' => $message, 'context' => $context];
            }
        );

        $svc = $this->service($logger);
        $svc->injectedConnections[] = $this->mockPdoThatSucceeds();

        $svc->select(
            'SELECT * FROM users WHERE password = :secret',
            [':secret' => 'hunter2-very-sensitive-value']
        );

        $this->assertNotEmpty($captured);
        foreach ($captured as $entry) {
            $serialised = $entry['message'] . ' ' . json_encode($entry['context']);
            $this->assertStringNotContainsString(
                'hunter2-very-sensitive-value',
                $serialised,
                'Logger output must not contain raw parameter values'
            );
        }
    }
}
