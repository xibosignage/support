<?php
/**
 * Copyright (c) 2026 Xibo Signage Ltd
 */

namespace Xibo\Support\Database;

/**
 * Interface StorageInterface
 * @package Xibo\Support\Database
 */
interface StorageServiceInterface
{
    /**
     * PDOConnect constructor.
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param array $config Connection configuration. Required keys: host, user, pass, name.
     *                      Optional keys: ssl (path to CA certificate or 'none' to disable),
     *                      sslVerify (bool, default true).
     */
    public function __construct($logger, $config);

    /**
     * Set a connection
     * @param string $name
     * @return $this
     */
    public function setConnection($name = 'default');

    /**
     * Closes the stored connection
     * @param string|null $name The name of the connection, or null for all connections
     */
    public function close($name = null);

    /**
     * Open a connection with the specified details
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string|null $name
     * @param string|null $ssl Path to CA certificate, or 'none'/null to disable TLS
     * @param bool $sslVerify Whether to verify the server certificate
     * @return \PDO
     */
    public function connect($host, $user, $pass, $name = null, $ssl = null, $sslVerify = true);

    /**
     * Get the Raw Connection
     * @param string $name The connection name
     * @return \PDO
     */
    public function getConnection($name = 'default');

    /**
     * Check to see if the query returns records
     * @param string $sql
     * @param array $params
     * @param string $connection
     * @param bool $reconnect Reconnect once on MySQL error 2006
     * @param bool $close Close the connection after the query completes
     * @return bool
     */
    public function exists($sql, $params, $connection = 'default', $reconnect = false, $close = false);

    /**
     * Run Insert SQL
     * @param string $sql
     * @param array $params
     * @param string $connection
     * @param bool $reconnect Reconnect once on MySQL error 2006
     * @param bool $transaction Begin a transaction if none is active
     * @param bool $close Close the connection after the query completes
     * @return int
     * @throws \PDOException
     */
    public function insert(
        $sql,
        $params,
        $connection = 'default',
        $reconnect = false,
        $transaction = true,
        $close = false
    );

    /**
     * Run Update SQL
     * @param string $sql
     * @param array $params
     * @param string $connection
     * @param bool $reconnect Reconnect once on MySQL error 2006
     * @param bool $transaction Begin a transaction if none is active
     * @param bool $close Close the connection after the query completes
     * @return int affected rows
     * @throws \PDOException
     */
    public function update(
        $sql,
        $params,
        $connection = 'default',
        $reconnect = false,
        $transaction = true,
        $close = false
    );

    /**
     * Run Select SQL
     * @param string $sql
     * @param array $params
     * @param string $connection
     * @param bool $reconnect Reconnect once on MySQL error 2006
     * @param bool $close Close the connection after the query completes
     * @return array
     * @throws \PDOException
     */
    public function select($sql, $params, $connection = 'default', $reconnect = false, $close = false);

    /**
     * Run SQL on the dedicated 'isolated' connection
     * @param string $sql
     * @param array $params
     * @param string $connection
     * @param bool $reconnect Reconnect once on MySQL error 2006
     * @param bool $close Close the connection after the query completes
     */
    public function isolated($sql, $params, $connection = 'isolated', $reconnect = false, $close = false);

    /**
     * Run the SQL statement with a deadlock loop (MySQL errors 1213/1205, max 2 retries).
     * @param string $sql
     * @param array $params
     * @param string $connection
     * @param bool $transaction Begin a transaction if none is active
     * @param bool $close Close the connection after the query completes
     * @throws \Xibo\Support\Exception\DeadLockException
     */
    public function updateWithDeadlockLoop($sql, $params, $connection = 'default', $transaction = true, $close = false);

    /**
     * Commit if necessary
     * @param string $name
     * @param bool $close Close the connection after the commit
     */
    public function commitIfNecessary($name = 'default', $close = false);

    /**
     * Set the TimeZone for this connection. The value is validated against a strict
     * allow-list (numeric offsets like '+05:30' or IANA-style names like
     * 'Europe/London') before being interpolated into the SET statement.
     * @param string $timeZone
     * @param string $connection
     * @throws \Xibo\Support\Exception\InvalidArgumentException When the value is not allow-listed
     */
    public function setTimeZone($timeZone, $connection = 'default');

    /**
     * PDO stats
     * @return array
     */
    public function stats();

    /**
     * @param string $connection
     * @param string $key
     */
    public function incrementStat($connection, $key);

    /**
     * Get the Storage engine version
     * @return string|null
     */
    public function getVersion();
}
