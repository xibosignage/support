<?php
/**
 * Copyright (c) 2026 Xibo Signage Ltd
 *
 * Originally derived from xibosignage/xibo-cms lib/Storage/PdoStorageService.php,
 * relicensed AGPL-3.0+ to MIT by the rights holder for inclusion in this library.
 */

namespace Xibo\Support\Database;

use Xibo\Support\Exception\DeadLockException;
use Xibo\Support\Exception\InvalidArgumentException;

/**
 * Class PDOConnect
 * Manages connection state and the creation of connections.
 * @package Xibo\Support\Database
 */
class PdoStorageService implements StorageServiceInterface
{
    /** @var \PDO[] An array of PDO connections keyed by name */
    private $conn = [];

    /** @var array Statistics */
    private static $stats = [];

    /** @var string|null MySQL version (cached) */
    private static $_version;

    /** @var \Psr\Log\LoggerInterface|null */
    private $log;

    /** @var array */
    private $config;

    /** @inheritDoc */
    public function __construct($logger, $config)
    {
        $this->log = $logger;
        $this->config = $config;
    }

    /** @inheritdoc */
    public function setConnection($name = 'default')
    {
        $this->conn[$name] = $this->openConnection();
        return $this;
    }

    /** @inheritdoc */
    public function close($name = null)
    {
        if ($name !== null && isset($this->conn[$name])) {
            $this->conn[$name] = null;
            unset($this->conn[$name]);
        } else {
            foreach ($this->conn as &$conn) {
                $conn = null;
            }
            $this->conn = [];
        }
    }

    /**
     * Build a MySQL DSN, validating host/name to prevent DSN parameter injection.
     * @param string $host May be 'host' or 'host:port'
     * @param string|null $name Database name
     * @return string
     * @throws InvalidArgumentException When host or name fails validation
     */
    private function createDsn($host, $name = null)
    {
        $hostName = $host;
        $port = null;
        if (strstr($host, ':')) {
            [$hostName, $port] = explode(':', $host, 2);
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $hostName)) {
            throw new InvalidArgumentException('Invalid database host', 'host');
        }
        if ($port !== null && !preg_match('/^\d+$/', $port)) {
            throw new InvalidArgumentException('Invalid database port', 'host');
        }
        if ($name !== null && $name !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new InvalidArgumentException('Invalid database name', 'name');
        }

        $dsn = 'mysql:host=' . $hostName . ';';
        if ($port !== null) {
            $dsn .= 'port=' . $port . ';';
        }
        if ($name !== null && $name !== '') {
            $dsn .= 'dbname=' . $name . ';';
        }
        // Charset in DSN avoids the SET NAMES / emulated-prepares charset desync,
        // and utf8mb4 is the proper 4-byte UTF-8 alias.
        $dsn .= 'charset=utf8mb4;';

        return $dsn;
    }

    /**
     * Open a connection using the constructor-injected config.
     * @return \PDO
     */
    private function openConnection()
    {
        $ssl = $this->config['ssl'] ?? null;
        $sslVerify = $this->config['sslVerify'] ?? true;
        return $this->connect(
            $this->config['host'],
            $this->config['user'],
            $this->config['pass'],
            $this->config['name'] ?? null,
            $ssl,
            $sslVerify
        );
    }

    /** @inheritDoc */
    public function connect($host, $user, $pass, $name = null, $ssl = null, $sslVerify = true)
    {
        $dsn = $this->createDsn($host, $name);

        $opts = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            // Disable emulated prepares so escaping is performed by the server using the
            // connection charset declared in the DSN. Closes the SET-NAMES/emulated-prepares
            // charset-desync class of SQL injection.
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if (!empty($ssl) && $ssl !== 'none') {
            $opts[\PDO::MYSQL_ATTR_SSL_CA] = $ssl;
            $opts[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool)$sslVerify;
        }

        return new \PDO($dsn, $user, $pass, $opts);
    }

    /** @inheritdoc */
    public function getConnection($name = 'default')
    {
        if (!isset($this->conn[$name])) {
            $this->conn[$name] = $this->openConnection();
        }

        return $this->conn[$name];
    }

    /** @inheritdoc */
    public function exists($sql, $params, $connection = 'default', $reconnect = false, $close = false)
    {
        $this->logSql($sql, $params);

        try {
            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);
            $exists = $sth->fetch();

            $this->incrementStat($connection, 'exists');

            if ($close) {
                $this->close($connection);
            }

            return (bool)$exists;
        } catch (\PDOException $PDOException) {
            if (!$reconnect) {
                throw $PDOException;
            }
            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();
            if ($errorCode != 2006) {
                throw $PDOException;
            }
            $this->close($connection);
            return $this->exists($sql, $params, $connection, false, $close);
        } catch (\ErrorException $exception) {
            // Catch "Error while sending QUERY packet."
            if (!$reconnect) {
                throw $exception;
            }
            $this->close($connection);
            return $this->exists($sql, $params, $connection, false, $close);
        }
    }

    /** @inheritdoc */
    public function insert(
        $sql,
        $params,
        $connection = 'default',
        $reconnect = false,
        $transaction = true,
        $close = false
    ) {
        $this->logSql($sql, $params);

        try {
            if ($transaction && !$this->getConnection($connection)->inTransaction()) {
                $this->getConnection($connection)->beginTransaction();
            }

            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);
            $id = intval($this->getConnection($connection)->lastInsertId());

            $this->incrementStat($connection, 'insert');

            if ($close) {
                $this->close($connection);
            }

            return $id;
        } catch (\PDOException $PDOException) {
            if (!$reconnect) {
                throw $PDOException;
            }
            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();
            if ($errorCode != 2006) {
                throw $PDOException;
            }
            $this->close($connection);
            return $this->insert($sql, $params, $connection, false, $transaction, $close);
        } catch (\ErrorException $exception) {
            if (!$reconnect) {
                throw $exception;
            }
            $this->close($connection);
            return $this->insert($sql, $params, $connection, false, $transaction, $close);
        }
    }

    /** @inheritdoc */
    public function update(
        $sql,
        $params,
        $connection = 'default',
        $reconnect = false,
        $transaction = true,
        $close = false
    ) {
        $this->logSql($sql, $params);

        try {
            if ($transaction && !$this->getConnection($connection)->inTransaction()) {
                $this->getConnection($connection)->beginTransaction();
            }

            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);

            $rows = $sth->rowCount();

            $this->incrementStat($connection, 'update');

            if ($close) {
                $this->close($connection);
            }

            return $rows;
        } catch (\PDOException $PDOException) {
            if (!$reconnect) {
                throw $PDOException;
            }
            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();
            if ($errorCode != 2006) {
                throw $PDOException;
            }
            $this->close($connection);
            return $this->update($sql, $params, $connection, false, $transaction, $close);
        } catch (\ErrorException $exception) {
            if (!$reconnect) {
                throw $exception;
            }
            $this->close($connection);
            return $this->update($sql, $params, $connection, false, $transaction, $close);
        }
    }

    /** @inheritdoc */
    public function select($sql, $params, $connection = 'default', $reconnect = false, $close = false)
    {
        $this->logSql($sql, $params);

        try {
            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);
            $records = $sth->fetchAll(\PDO::FETCH_ASSOC);

            $this->incrementStat($connection, 'select');

            if ($close) {
                $this->close($connection);
            }

            return $records;
        } catch (\PDOException $PDOException) {
            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();

            // Syntax errors are programmer errors; surface them at error level.
            if ($errorCode == 1064) {
                $this->logSql($sql, $params, true);
            }

            if (!$reconnect) {
                throw $PDOException;
            }
            if ($errorCode != 2006) {
                throw $PDOException;
            }
            $this->close($connection);
            return $this->select($sql, $params, $connection, false, $close);
        } catch (\ErrorException $exception) {
            if (!$reconnect) {
                throw $exception;
            }
            $this->close($connection);
            return $this->select($sql, $params, $connection, false, $close);
        }
    }

    /** @inheritdoc */
    public function isolated($sql, $params, $connection = 'isolated', $reconnect = false, $close = false)
    {
        $this->logSql($sql, $params);

        try {
            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);

            $this->incrementStat($connection, 'update');

            if ($close) {
                $this->close($connection);
            }
        } catch (\PDOException $PDOException) {
            if (!$reconnect) {
                throw $PDOException;
            }
            $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();
            if ($errorCode != 2006) {
                throw $PDOException;
            }
            $this->close($connection);
            $this->isolated($sql, $params, $connection, false, $close);
        } catch (\ErrorException $exception) {
            if (!$reconnect) {
                throw $exception;
            }
            $this->close($connection);
            $this->isolated($sql, $params, $connection, false, $close);
        }
    }

    /** @inheritdoc */
    public function updateWithDeadlockLoop($sql, $params, $connection = 'default', $transaction = true, $close = false)
    {
        $maxRetries = 2;

        $this->logSql($sql, $params);

        if ($transaction && !$this->getConnection($connection)->inTransaction()) {
            $this->getConnection($connection)->beginTransaction();
        }

        $statement = $this->getConnection($connection)->prepare($sql);

        $success = false;
        $retries = $maxRetries;
        do {
            try {
                $this->incrementStat($connection, 'update');
                $statement->execute($params);
                $success = true;
            } catch (\PDOException $PDOException) {
                $errorCode = $PDOException->errorInfo[1] ?? $PDOException->getCode();

                if ($errorCode != 1213 && $errorCode != 1205) {
                    throw $PDOException;
                }
            }

            if ($success) {
                break;
            }

            if ($this->log !== null) {
                $queryHash = substr($sql, 0, 15) . '... [' . md5($sql . json_encode($params)) . ']';
                $this->log->debug(
                    'Retrying query after a short nap, try: ' . (3 - $retries) . '. Query Hash: ' . $queryHash
                );
            }
            usleep(10000);
        } while ($retries--);

        if (!$success) {
            throw new DeadLockException(
                'Failed to write to database after ' . $maxRetries . ' retries. Please try again later.'
            );
        }

        if ($close) {
            $this->close($connection);
        }
    }

    /** @inheritdoc */
    public function commitIfNecessary($name = 'default', $close = false)
    {
        if ($this->getConnection($name)->inTransaction()) {
            $this->incrementStat($name, 'commit');
            $this->getConnection($name)->commit();
        }

        if ($close) {
            $this->close($name);
        }
    }

    /** @inheritDoc */
    public function setTimeZone($timeZone, $connection = 'default')
    {
        // Reject anything that isn't a numeric offset (e.g. -08:00, +05:30) or an
        // IANA-style name (e.g. UTC, Europe/London). MySQL does not allow placeholders
        // in `SET time_zone = ?`, so we have to interpolate — the validation below
        // closes that injection vector.
        $isOffset = preg_match('/^[+-]?\d{1,2}:\d{2}$/', $timeZone) === 1;
        $isNamed = preg_match('/^[A-Za-z][A-Za-z0-9_+\-\/]*$/', $timeZone) === 1;
        if (!$isOffset && !$isNamed) {
            throw new InvalidArgumentException('Invalid time zone', 'timeZone');
        }

        $this->getConnection($connection)->query('SET time_zone = \'' . $timeZone . '\';');

        $this->incrementStat($connection, 'utility');
    }

    /** @inheritDoc */
    public function stats()
    {
        return self::$stats;
    }

    /** @inheritDoc */
    public function incrementStat($connection, $key)
    {
        $currentCount = self::$stats[$connection][$key] ?? 0;
        self::$stats[$connection][$key] = $currentCount + 1;
    }

    /** @inheritDoc */
    public function getVersion()
    {
        if (self::$_version === null) {
            $results = $this->select('SELECT version() AS v', []);

            if (count($results) <= 0) {
                return null;
            }

            self::$_version = explode('-', $results[0]['v'])[0];
        }

        return self::$_version;
    }

    /**
     * Log a SQL statement with parameter values redacted. We log the SQL with its
     * placeholders intact and a separate context array containing only the parameter
     * keys — never the values — to avoid leaking secrets/PII into log files.
     * @param string $sql
     * @param array $params
     * @param bool $isError When true, log at error level (e.g. for syntax errors)
     */
    private function logSql($sql, $params, $isError = false)
    {
        if ($this->log === null) {
            return;
        }

        $context = ['paramKeys' => array_keys($params ?? [])];
        $message = 'SQL: ' . $sql;

        if ($isError) {
            $this->log->error($message, $context);
        } else {
            $this->log->debug($message, $context);
        }
    }
}
