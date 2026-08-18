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
    private const DEFAULT_HOST    = 'localhost';
    private const DEFAULT_PORT    = 3306;

    /** @var bool Indicates whether this instance has already started a transaction. */
    private bool $in_transaction = false;

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
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $unix_socket, $db_name, $charset)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $db_name, $charset);

        $options ??= [];
        $options[PDO::ATTR_ERRMODE] ??= PDO::ERRMODE_EXCEPTION;

        parent::__construct($dsn, $user, $password, $options);
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param  Closure(PDO): mixed $callback
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
            $this->safeRollback($e);
            throw $e;
        }
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
     */
    private function safeRollback(Throwable $original): void
    {
        try {
            $this->rollbackThis();
        } catch (Throwable $rollback_exception) {
            error_log(sprintf(
                'Transaction rollback failed after [%s]: %s',
                $original->getMessage(),
                $rollback_exception->getMessage()
            ));
        }
    }

    /**
     * Verify that the connection's error mode is set to throw exceptions.
     */
    private function checkErrorModeEnabled(): void
    {
        if ($this->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new RuntimeException(
                'PDO error mode is not set to throw exceptions. '
                    . 'Please set PDO::ATTR_ERRMODE to PDO::ERRMODE_EXCEPTION.'
            );
        }
    }
}
