<?php

declare(strict_types=1);

namespace rguezque\Database;

use Closure;
use InvalidArgumentException;
use LogicException;
use PDO;
use rguezque\Contract\ConnectionInterface;
use RuntimeException;
use Throwable;

final class PDOConnection extends PDO implements ConnectionInterface
{
    /** @var string Default charset */
    private const DEFAULT_CHARSET = 'utf8mb4';

    /** @var string Default host */
    private const DEFAULT_HOST = 'localhost';

    /** @var int Default port */
    private const DEFAULT_PORT = 3306;

    /** @var bool Indicates whether this instance has already started a transaction. */
    private bool $in_transaction = false;

    /**
     * Initialize a PDO connection.
     *
     * @param string $db_name Database name for connection.
     * @param ?string $user Database connection username.
     * @param ?string $password Database connection password.
     * @param string $host Database host.
     * @param int $port Database connection port.
     * @param ?string $unix_socket Connection socket. If defined, `$host` will be ignored.
     * @param string $charset Defines the character encoding that will be used to send and receive data between PHP and the database.
     * @param array<int|string, int|string|bool> $options Connection options.
     * @throws InvalidArgumentException If db_name or charset contain invalid characters.
     */
    public function __construct(
        string $db_name,
        ?string $user = null,
        ?string $password = null,
        string $host = self::DEFAULT_HOST,
        int $port = self::DEFAULT_PORT,
        ?string $unix_socket = null,
        string $charset = self::DEFAULT_CHARSET,
        array $options = [],
    ) {
        $charset = trim($charset);
        if ('' === $charset) {
            $charset = self::DEFAULT_CHARSET;
        }

        self::validateIdentifier($db_name, 'db_name');
        self::validateIdentifier($charset, 'charset');

        if ($unix_socket !== null) {
            self::validateIdentifier($unix_socket, 'unix_socket');
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $unix_socket, $db_name, $charset);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $db_name, $charset);
        }

        $options[PDO::ATTR_ERRMODE] ??= PDO::ERRMODE_EXCEPTION;

        parent::__construct($dsn, $user, $password, $options);
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param  Closure(ConnectionInterface): mixed $callback The callback with the business logic.
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

    /**
     * Validate that a database identifier contains only safe characters.
     *
     * @param string $value The value to validate.
     * @param string $field The field name (for error messages).
     * @throws InvalidArgumentException
     */
    private static function validateIdentifier(string $value, string $field): void
    {
        if ($value === '') {
            return;
        }
        if (!preg_match('/^[A-Za-z0-9_\-]+$/', $value)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The "%s" parameter contains invalid characters. Only letters, numbers, underscores and hyphens are allowed.',
                    $field
                ),
                400
            );
        }
    }
}
