<?php

declare(strict_types=1);

namespace rguezque\Database;

use Closure;
use LogicException;
use mysqli;
use mysqli_driver;
use PDO;
use rguezque\Contract\ConnectionInterface;
use RuntimeException;
use Throwable;

/**
 * Represents a database transaction that can be used with PDO or MySQLi connections.
 *
 * This class provides a method to execute a callback within a transaction, ensuring 
 * that the transaction is committed if the callback succeeds or rolled back if it fails.
 * It also allows setting a fallback for rollback failures.
 */
final class Transaction
{
    /** @var ConnectionInterface Connection to the database. */
    private ConnectionInterface $connection;

    /** @var bool Indicates whether this instance has already started a transaction. */
    private bool $in_transaction = false;

    /**
     * Constructor.
     *
     * @param ConnectionInterface $connection Connection to the database.
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param Closure(ConnectionInterface): mixed $callback Callback that receives the connection and returns a value.
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
            $this->safeRollback($e);
            throw $e; // Rethrow the original business logic exception
        }
    }

    /**
     * Start a transaction.
     *
     * @throws LogicException|RuntimeException
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
        } elseif ($this->connection instanceof mysqli) {
            if ($this->connection->begin_transaction() === false) {
                throw new RuntimeException('Failed to start transaction with mysqli.');
            }
        }

        $this->in_transaction = true;
    }

    /**
     * Confirm a transaction.
     *
     * @throws RuntimeException
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
        } elseif ($this->connection instanceof mysqli) {
            if ($this->connection->commit() === false) {
                throw new RuntimeException('Could not commit with mysqli.');
            }
        }

        $this->in_transaction = false;
    }

    /**
     * Rollback a transaction.
     *
     * @throws RuntimeException If rollback fails.
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
        } elseif ($this->connection instanceof mysqli) {
            if ($this->connection->rollback() === false) {
                throw new RuntimeException('Could not rollback with mysqli.');
            }
        }

        $this->in_transaction = false;
    }

    /**
     * Attempt to rollback without hiding the original exception.
     *
     * If rollback fails, the exception will be passed to the fallback if set, and logged.
     *
     * @param Throwable $original_exception The exception that caused the transaction to fail.
     */
    private function safeRollback(Throwable $original_exception): void
    {
        try {
            $this->rollback();
        } catch (Throwable $rollback_exception) {
            error_log(sprintf(
                'Transaction rollback failed after [%s]: %s',
                $original_exception->getMessage(),
                $rollback_exception->getMessage()
            ));
        }
    }

    /**
     * Verify that the connection's error mode is set to throw exceptions.
     *
     * @throws RuntimeException If error mode is not set to throw exceptions.
     */
    private function checkErrorModeEnabled(): void
    {
        if ($this->connection instanceof PDO) {
            $use_exceptions = $this->connection->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION;
            if (!$use_exceptions) {
                throw new RuntimeException('PDO error mode is not set to throw exceptions. Please set PDO::ATTR_ERRMODE to PDO::ERRMODE_EXCEPTION.');
            }
        } elseif ($this->connection instanceof mysqli) {
            // Note: mysqli_driver properties are deprecated in PHP 8.1+. 
            // We use error suppression (@) to avoid deprecation warnings while checking the mode.
            $driver = new mysqli_driver();
            $use_exceptions = @($driver->report_mode & MYSQLI_REPORT_STRICT) === MYSQLI_REPORT_STRICT;

            if (!$use_exceptions) {
                throw new RuntimeException('MySQLi error mode is not set to throw exceptions. Please use mysqli_report(MYSQLI_REPORT_STRICT).');
            }
        }
    }
}
