<?php

declare(strict_types=1);

namespace rguezque\Database;

use Closure;
use LogicException;
use mysqli;
use mysqli_driver;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Represents a database transaction that can be used with PDO or MySQLi connections.
 * 
 * This class provides a method to execute a callback within a transaction, ensuring that the transaction is committed if the callback succeeds or rolled back if it fails.
 * It also allows setting a fallback for rollback failures.
 * 
 * @method __construct(PDO|mysqli $connection) Constructor that accepts a PDO or MySQLi connection.
 * @method mixed transactional(Closure(PDO|mysqli): mixed $callback) Execute a callback within a transaction.
 * @method void setFallback(Closure $fallback) Assigns a fallback to execute in case the rollback fails.
 */
final class Transaction
{
    /** @var PDO|mysqli Connection to the database. */
    private PDO|mysqli $connection;

    /** @var bool Indicates whether this instance has already started a transaction. */
    private bool $in_transaction = false;

    /** @var Closure|null Fallback for the rollback. */
    private $fallback;

    /**
     * Constructor.
     * 
     * @param PDO|mysqli $connection Connection to the database.
     */
    public function __construct(PDO|mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param Closure(PDO|mysqli): mixed $callback Callback that receives the connection and returns a value.
     * @return mixed What the callback returns.
     * @throws Throwable If the callback or commit fails.
     */
    public function transactional(Closure $callback): mixed
    {
        $this->checkErrorModeEnabled();
        
        $this->begin();

        try {
            $result = $callback($this->connection);
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->safeRollback();

            throw $e;
        }
    }

    /**
     * Assigns a fallback to execute in case the safe rollback fails. Must be set before calling `transactional()`. 
     * The exception will be passed to the fallback as an argument. If no fallback is set, the exception will 
     * be logged using `error_log`.
     *
     * @param Closure $fallback Fallback for the rollback.
     * @return void
     */
    public function setFallback(Closure $fallback): void
    {
        $this->fallback = $fallback;
    }

    /**
     * Start a transaction.
     */
    private function begin(): void
    {
        if ($this->in_transaction) {
            throw new LogicException('There is already an active transaction in this instance.');
        }

        if ($this->connection instanceof PDO) {
            if ($this->connection->inTransaction()) {
                throw new LogicException('The PDO connection already has an active transaction.');
            }

            if ($this->connection->beginTransaction() === false) {
                throw new RuntimeException('Failed to start transaction with PDO.');
            }
        } else {
            // mysqli
            if ($this->connection->begin_transaction() === false) {
                throw new RuntimeException('Failed to start transaction with mysqli.');
            }
        }

        $this->in_transaction = true;
    }

    /**
     * Confirm a transaction.
     */
    private function commit(): void
    {
        if (!$this->in_transaction) {
            return;
        }

        if ($this->connection instanceof PDO) {
            // If for some reason there is no longer an active transaction, we avoid error.
            if (!$this->connection->inTransaction()) {
                $this->in_transaction = false;

                return;
            }

            if ($this->connection->commit() === false) {
                throw new RuntimeException('Could not commit with PDO.');
            }
        } else {
            // mysqli
            if ($this->connection->commit() === false) {
                throw new RuntimeException('Could not commit with mysqli.');
            }
        }

        $this->in_transaction = false;
    }

    /**
     * Rollback a transaction.
     */
    private function rollback(): void
    {
        if (!$this->in_transaction) {
            return;
        }

        if ($this->connection instanceof PDO) {
            if (!$this->connection->inTransaction()) {
                $this->in_transaction = false;

                return;
            }

            if ($this->connection->rollBack() === false) {
                throw new RuntimeException('Could not rollback with PDO.');
            }
        } else {
            // mysqli
            if ($this->connection->rollback() === false) {
                throw new RuntimeException('Could not rollback with mysqli.');
            }
        }

        $this->in_transaction = false;
    }

    /**
     * Attempt to rollback without hiding the original exception.
     */
    private function safeRollback(): void
    {
        try {
            $this->rollback();
        } catch (Throwable $rollback_exception) {
            // In production, the ideal is to send this to a logger.
            if($this->fallback) {
                call_user_func($this->fallback, $rollback_exception);
            } else {
                error_log(
                    sprintf(
                        'Transaction rollback failed: %s',
                        $rollback_exception->getMessage()
                    )
                );
            }
        }
    }

    /**
     * Verify that the connection's error mode is set to throw exceptions.
     * 
     * @throws RuntimeException If error mode is not set to throw exceptions.
     */
    private function checkErrorModeEnabled()
    {
        if ($this->connection instanceof PDO) {
            $use_exceptions = $this->connection->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION;
            if(!$use_exceptions) {
                throw new RuntimeException('PDO error mode is not set to throw exceptions. Please set PDO::ATTR_ERRMODE to PDO::ERRMODE_EXCEPTION.');
            }
        } else {
            // mysqli
            $driver = new mysqli_driver();
            $use_exceptions = ($driver->report_mode & MYSQLI_REPORT_STRICT) === MYSQLI_REPORT_STRICT;

            if (!$use_exceptions) {
                throw new RuntimeException('MySQLi error mode is not set to throw exceptions. Please set MYSQLI_REPORT_STRICT.');
            }
        }
    }
}
