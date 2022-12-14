<?php

declare(strict_types=1);

namespace LTS\DsmRuntime\EntityManager\RetryConnection;

use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\ParameterType;
use IteratorAggregate;
use PDO;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Statement implements IteratorAggregate, DriverStatement
{
    /**
     * @var PingingAndReconnectingConnection
     */
    private $connection;
    /**
     * @var array
     */
    private $params = [];
    /**
     * @var string
     */
    private $sql;
    /**y
     *
     * @var array
     */
    private $values = [];
    /**
     * @TODO Required setting as mixed due to stan reasons, pending a better solution
     * @var \Doctrine\DBAL\Statement
     */
    private $wrappedStatement;

    /**
     * @param string                           $sql
     * @param PingingAndReconnectingConnection $conn
     */
    public function __construct(
        string                           $sql,
        PingingAndReconnectingConnection $conn
    ) {
        $this->sql        = $sql;
        $this->connection = $conn;
        $this->createStatement();
    }

    /**
     * Create Statement.
     */
    private function createStatement()
    {
        $this->wrappedStatement = $this->connection->prepareUnwrapped($this->sql);
        foreach ($this->params as $params) {
            $this->bindParam(...$params);
        }
        $this->params = [];
        foreach ($this->values as $values) {
            $this->bindValue(...$values);
        }
        $this->values = [];
    }

    /**
     * @inheritdoc
     */
    public function bindParam($column, &$variable, $type = ParameterType::STRING, $length = null)
    {
        $this->params[] = [$column, $variable, $type, $length];

        return $this->wrappedStatement->bindParam($column, $variable, $type, $length);
    }

    public function executeStatement(array $params = []): int
    {
        return $this->wrappedStatement->executeStatement($params);
    }

    /**
     * @inheritdoc
     */
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $this->values[] = [$param, $value, $type];

        return $this->wrappedStatement->bindValue($param, $value, $type);
    }

    /**
     * @inheritdoc
     */
    public function closeCursor()
    {
        return $this->wrappedStatement->closeCursor();
    }

    /**
     * @inheritdoc
     */
    public function columnCount()
    {
        return $this->wrappedStatement->columnCount();
    }

    /**
     * @inheritdoc
     */
    public function errorCode()
    {
        return $this->wrappedStatement->errorCode();
    }

    /**
     * @inheritdoc
     */
    public function errorInfo()
    {
        return $this->wrappedStatement->errorInfo();
    }

    /**
     * @inheritdoc
     */
    public function execute($params = null)
    {
        return $this->wrappedStatement->execute($params);
    }

    /**
     * @inheritdoc
     */
    public function fetch($fetchMode = null, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
    {
        return $this->wrappedStatement->fetch($fetchMode, $cursorOrientation, $cursorOffset);
    }

    /**
     * @inheritdoc
     */
    public function fetchAll($fetchMode = null, $fetchArgument = null, $ctorArgs = null)
    {
        return $this->wrappedStatement->fetchAll($fetchMode, $fetchArgument, $ctorArgs);
    }

    /**
     * @inheritdoc
     */
    public function fetchColumn($columnIndex = 0)
    {
        return $this->wrappedStatement->fetchColumn($columnIndex);
    }

    /**
     * @inheritdoc
     */
    public function getIterator()
    {
        return $this->wrappedStatement->getIterator();
    }

    /**
     * @inheritdoc
     */
    public function rowCount()
    {
        return $this->wrappedStatement->rowCount();
    }

    /**
     * @inheritdoc
     */
    public function setFetchMode($fetchMode, $arg2 = null, $arg3 = null)
    {
        return $this->wrappedStatement->setFetchMode($fetchMode, $arg2, $arg3);
    }
}
