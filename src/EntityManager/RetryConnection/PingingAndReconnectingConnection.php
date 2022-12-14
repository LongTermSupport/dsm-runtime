<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\EntityManager\RetryConnection;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver;
use ReflectionProperty;
use ts\Reflection\ReflectionClass;

/**
 * This is a connection wrapper that enables some retry functionality should the connection to the DB be lost for any
 * reason. Especially useful on long running processes
 */
class PingingAndReconnectingConnection extends Connection
{
    /**
     * How many seconds between pings
     *
     * @var float
     */
    private const PING_INTERVAL_SECONDS = 1.0;

    private const PING_FAILURE_SLEEP_SECONDS = 10;

    /** @var ReflectionProperty */
    private $selfReflectionNestingLevelProperty;

    /** @var float */
    private $pingTimer = 0;

    public function executeUpdate($query, array $params = [], array $types = [])
    {
        $args = [$query, $params, $types];

        return $this->pingBeforeMethodCall(__FUNCTION__, $args);
    }

    private function pingBeforeMethodCall(string $function, array $args)
    {
        $this->pingAndReconnectOnFailure();

        return parent::$function(...$args);
    }

    public function pingAndReconnectOnFailure(): void
    {
        if (microtime(true) < ($this->pingTimer + self::PING_INTERVAL_SECONDS)) {
            return;
        }
        $this->pingTimer = microtime(true);
        if (false === $this->ping()) {
            $this->close();
            $this->resetTransactionNestingLevel();
            sleep(self::PING_FAILURE_SLEEP_SECONDS);
            parent::connect();
        }
    }

    /**
     * Overriding the ping method so we explicitly call the raw unwrapped methods as required, otherwise we go into
     * infinite loop
     *
     * @return bool
     */
    public function ping(): bool
    {
        parent::connect();

        if ($this->_conn instanceof Driver\PingableConnection) {
            return $this->_conn->ping();
        }

        try {
            parent::query($this->getDatabasePlatform()->getDummySelectSQL());

            return true;
        } catch (DBALException $e) {
            return false;
        }
    }


    /**
     * This is required because beginTransaction increment _transactionNestingLevel
     * before the real query is executed, and results incremented also on gone away error.
     * This should be safe for a new established connection.
     */
    private function resetTransactionNestingLevel(): void
    {
        if (!$this->selfReflectionNestingLevelProperty instanceof ReflectionProperty) {
            $reflection                               = new ReflectionClass(Connection::class);
            $this->selfReflectionNestingLevelProperty = $reflection->getProperty('transactionNestingLevel');
            $this->selfReflectionNestingLevelProperty->setAccessible(true);
        }

        $this->selfReflectionNestingLevelProperty->setValue($this, 0);
    }

    public function query(...$args)
    {
        return $this->pingBeforeMethodCall(__FUNCTION__, $args);
    }

    public function executeQuery($query, array $params = [], $types = [], QueryCacheProfile $qcp = null)
    {
        $args = [$query, $params, $types, $qcp];

        return $this->pingBeforeMethodCall(__FUNCTION__, $args);
    }

    public function beginTransaction()
    {
        $this->pingBeforeMethodCall(__FUNCTION__, []);
    }

    /**
     * @param string $sql
     *
     * @return Statement
     */
    public function prepare($sql): Statement
    {
        return $this->prepareWrapped($sql);
    }

    /**
     * returns a reconnect-wrapper for Statements.
     *
     * @param string $sql
     *
     * @return Statement
     */
    protected function prepareWrapped(string $sql): Statement
    {
        $this->pingAndReconnectOnFailure();

        return new Statement($sql, $this);
    }

    /**
     * do not use, only used by Statement-class
     * needs to be public for access from the Statement-class.
     *
     * @param string $sql
     *
     * @return Driver\Statement
     * @throws DBALException
     *@internal
     *
     */
    public function prepareUnwrapped(string $sql): Driver\Statement
    {
        // returns the actual statement
        return parent::prepare($sql);
    }
}
