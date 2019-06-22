<?php
/**
 * Copyright (c) 2019 Xibo Signage Ltd
 */

namespace Xibo\Support\Database;


use Xibo\Support\Exception\DeadLockException;

/**
 * Class PDOConnect
 * Manages global connection state and the creation of connections
 * @package Xibo\Storage
 */
class PdoStorageService implements StorageServiceInterface
{
    /** @var array An array of PDO connections */
    private $conn = [];

    /** @var array Statistics */
    private static $stats = [];

    /** @var  string MySQL version number */
    private static $_version;

    /** @var \Psr\Log\LoggerInterface */
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
        // Create a new connection
        $this->conn[$name] = $this->connect(
            $this->config['host'],
            $this->config['user'],
            $this->config['pass'],
            $this->config['name']);
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
     * Create a DSN from the host/db name
     * @param string $host
     * @param string[Optional] $name
     * @return string
     */
    private function createDsn($host, $name = null)
    {
        if (strstr($host, ':')) {
            $hostParts = explode(':', $host);
            $dsn = 'mysql:host=' . $hostParts[0] . ';port=' . $hostParts[1] . ';';
        } else {
            $dsn = 'mysql:host=' . $host . ';';
        }

        if ($name != null) {
            $dsn .= 'dbname=' . $name . ';';
        }

        return $dsn;
    }

    /** @inheritDoc */
    public function connect($host, $user, $pass, $name = null)
    {
        $dsn = $this->createDsn($host, $name);

        // Open the connection and set the error mode
        $pdo = new \PDO($dsn, $user, $pass);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->query("SET NAMES 'utf8'");

        return $pdo;
    }

    /** @inheritdoc */
    public function getConnection($name = 'default')
    {
        if (!isset($this->conn[$name])) {
            $this->conn[$name] = $this->connect(
                $this->config['host'],
                $this->config['user'],
                $this->config['pass'],
                $this->config['name']);
        }

        return $this->conn[$name];
    }

    /** @inheritdoc */
    public function exists($sql, $params, $connection = null, $reconnect = false)
    {
        $this->logSql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            $sth = $this->getConnection($connection)->prepare($sql);
            $sth->execute($params);

            $this->incrementStat($connection, 'exists');

            if ($sth->fetch())
                return true;
            else
                return false;

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->exists($sql, $params, $connection, false);
            }
        }
    }

    /** @inheritdoc */
    public function insert($sql, $params, $connection = null, $reconnect = false)
    {
        $this->logSql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            if (!$this->getConnection($connection)->inTransaction())
                $this->getConnection($connection)->beginTransaction();

            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $this->incrementStat($connection, 'insert');

            return intval($this->getConnection($connection)->lastInsertId());

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->insert($sql, $params, $connection, false);
            }
        }
    }

    /** @inheritdoc */
    public function update($sql, $params, $connection = null, $reconnect = false)
    {
        $this->logSql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            if (!$this->getConnection($connection)->inTransaction())
                $this->getConnection($connection)->beginTransaction();

            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $rows = $sth->rowCount();

            $this->incrementStat($connection, 'update');

            return $rows;

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->update($sql, $params, $connection, false);
            }
        }
    }

    /** @inheritdoc */
    public function select($sql, $params, $connection = null, $reconnect = false)
    {
        $this->logSql($sql, $params);

        if ($connection === null)
            $connection = 'default';

        try {
            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $this->incrementStat($connection, 'select');

            return $sth->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->select($sql, $params, $connection, false);
            }
        }
    }

    /** @inheritdoc */
    public function isolated($sql, $params, $connection = null, $reconnect = false)
    {
        // Should we log?
        $this->logSql($sql, $params);

        if ($connection === null)
            $connection = 'isolated';

        try {
            $sth = $this->getConnection($connection)->prepare($sql);

            $sth->execute($params);

            $this->incrementStat('isolated', 'update');

        } catch (\PDOException $PDOException) {
            // Throw if we're not expected to reconnect.
            if (!$reconnect)
                throw $PDOException;

            $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

            if ($errorCode != 2006) {
                throw $PDOException;
            } else {
                $this->close($connection);
                return $this->isolated($sql, $params, $connection, false);
            }
        }
    }

    /** @inheritdoc */
    public function updateWithDeadlockLoop($sql, $params, $connection = null)
    {
        $maxRetries = 2;

        // Should we log?
        $this->logSql($sql, $params);

        if ($connection === null)
            $connection = 'isolated';

        // Prepare the statement
        $statement = $this->getConnection($connection)->prepare($sql);

        // Deadlock protect this statement
        $success = false;
        $retries = $maxRetries;
        do {
            try {
                $this->incrementStat($connection, 'update');
                $statement->execute($params);
                // Successful
                $success = true;

            } catch (\PDOException $PDOException) {
                $errorCode = isset($PDOException->errorInfo[1]) ? $PDOException->errorInfo[1] : $PDOException->getCode();

                if ($errorCode != 1213 && $errorCode != 1205)
                    throw $PDOException;
            }

            if ($success)
                break;

            // Sleep a bit, give the DB time to breathe
            $queryHash = substr($sql, 0, 15) . '... [' . md5($sql . json_encode($params)) . ']';
            $this->log->debug('Retrying query after a short nap, try: ' . (3 - $retries) . '. Query Hash: ' . $queryHash);
            usleep(10000);

        } while ($retries--);

        if (!$success) {
            throw new DeadLockException('Failed to write to database after ' . $maxRetries . ' retries. Please try again later.');
        }
    }

    /** @inheritdoc */
    public function commitIfNecessary($name = 'default')
    {
        if ($this->getConnection($name)->inTransaction()) {
            $this->incrementStat($name, 'commit');
            $this->getConnection($name)->commit();
        }
    }

    /** @inheritDoc */
    public function setTimeZone($timeZone, $connection = 'default')
    {
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
        $currentCount = (isset(self::$stats[$connection][$key])) ? self::$stats[$connection][$key] : 0;
        self::$stats[$connection][$key] = $currentCount + 1;
    }

    /** @inheritDoc */
    public function getVersion()
    {
        if (self::$_version === null) {

            $results = $this->select('SELECT version() AS v', []);

            if (count($results) <= 0)
                return null;

            self::$_version = explode('-', $results[0]['v'])[0];
        }

        return self::$_version;
    }

    /**
     * Format and log a SQL statement
     * @param $sql
     * @param $params
     */
    private function logSql($sql, $params)
    {
        if ($this->log != null) {
            $paramSql = '';
            foreach ($params as $key => $param) {
                $paramSql .= 'SET @' . $key . '=\'' . $param . '\';' . PHP_EOL;
            }
            $this->log->debug($paramSql . str_replace(':', '@', $sql));
        }
    }
}