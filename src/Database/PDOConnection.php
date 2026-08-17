<?php

declare(strict_types=1);

namespace rguezque\Database;

use Closure;
use LogicException;
use PDO;
use rguezque\Contract\ConnectionInterface;
use RuntimeException;
use Throwable;

final class PDOConnection extends PDO implements ConnectionInterface
{
    private const DEFAULT_CHARSET = 'utf8mb4';
    private const DEFAULT_HOST = 'localhost';
    private const DEFAULT_PORT = 3306;

    /** @var bool Indicates whether this instance has already started a transaction. */
    private bool $in_transaction = false;

    /** @var Closure|null Fallback for the rollback. */
    private $fallback;

    public function __construct(
        string $db_name,
        string $host = self::DEFAULT_HOST,
        int $port = self::DEFAULT_PORT,
        ?string $unix_socket = null,
        ?string $user = null,
        ?string $password = null,
        string $charset = self::DEFAULT_CHARSET,
        ?array $options = null,
    ) {
        $charset = trim($charset);

        if ('' === $charset) {
            $charset = self::DEFAULT_CHARSET;
        }

        $dsn = $unix_socket !== null
            ? sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $unix_socket,
                $db_name,
                $charset
            )
            : sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $host,
                $port,
                $db_name,
                $charset
            );

        parent::__construct(
            $dsn,
            $user,
            $password,
            $options
        );
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param Closure(PDO): mixed $callback Callback that receives the connection and returns a value.
     * @return mixed What the callback returns.
     * @throws Throwable If the callback or commit fails.
     */
    public function transactional(Closure $callback): mixed
    {
        $this->checkErrorModeEnabled();
        $this->beginThis();

        try {
            $result = $callback($this);
            $this->commitThis();

            return $result;
        } catch (Throwable $e) {
            $this->safeRollback();

            throw $e;
        }
    }

    /**
     * Assigns a fallback to execute in case the safe rollback fails. Must be set before calling `transactional()`. 
     * The exception will be passed to the fallback as an argument. If no fallback is set, the exception only will 
     * be logged to PHP logger system, using `error_log`.
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
    private function beginThis(): void
    {
        if ($this->in_transaction) {
            throw new LogicException('There is already an active transaction in this instance.');
        }

        if ($this->inTransaction()) {
            throw new LogicException('The PDO connection already has an active transaction.');
        }

        if ($this->beginTransaction() === false) {
            throw new RuntimeException('Failed to start transaction with PDO.');
        }

        $this->in_transaction = true;
    }

    /**
     * Confirm a transaction.
     */
    private function commitThis(): void
    {
        if (!$this->in_transaction) {
            return;
        }

        // If for some reason there is no longer an active transaction, we avoid error.
        if (!$this->inTransaction()) {
            $this->in_transaction = false;

            return;
        }

        if ($this->commit() === false) {
            throw new RuntimeException('Could not commit with PDO.');
        }

        $this->in_transaction = false;
    }

    /**
     * Rollback a transaction.
     * 
     * @throws RuntimeException If rollback fails.
     */
    private function rollbackThis(): void
    {
        if (!$this->in_transaction) {
            return;
        }

        if (!$this->inTransaction()) {
            $this->in_transaction = false;

            return;
        }

        if ($this->rollBack() === false) {
            throw new RuntimeException('Could not rollback with PDO.');
        }

        $this->in_transaction = false;
    }

    /**
     * Attempt to rollback without hiding the original exception.
     * 
     * If rollback fails, the exception will be passed to the fallback if set, and logged using `error_log`.
     */
    private function safeRollback(): void
    {
        try {
            $this->rollbackThis();
        } catch (Throwable $rollback_exception) {
            // In production, the ideal is to send this to a logger.
            if ($this->fallback) {
                call_user_func($this->fallback, $rollback_exception);
            }

            error_log(
                sprintf(
                    'Transaction rollback failed: %s',
                    $rollback_exception->getMessage()
                )
            );
        }
    }

    /**
     * Verify that the connection's error mode is set to throw exceptions.
     * 
     * @throws RuntimeException If error mode is not set to throw exceptions.
     */
    private function checkErrorModeEnabled()
    {
        $use_exceptions = $this->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_EXCEPTION;
        if (!$use_exceptions) {
            throw new RuntimeException('PDO error mode is not set to throw exceptions. Please set PDO::ATTR_ERRMODE to PDO::ERRMODE_EXCEPTION.');
        }
    }
}
