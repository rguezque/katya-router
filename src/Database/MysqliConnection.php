<?php

declare(strict_types=1);

namespace rguezque\Database;

use Closure;
use LogicException;
use mysqli;
use mysqli_driver;
use mysqli_sql_exception;
use rguezque\Contract\ConnectionInterface;
use RuntimeException;
use Throwable;

final class MysqliConnection extends mysqli implements ConnectionInterface
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
     * Initialize a mysqli connection.
     * 
     * @param string $db_name Database name for connection.
     * @param ?string $user Database connection username.
     * @param ?string $password Database connection password.
     * @param string $host Database host.
     * @param int $port Database connection port.
     * @param ?string $unix_socket Connection socket. If defined, `$host` must be `"localhost"`.
     * @param string $charset Defines the character encoding that will be used to send and receive data between PHP and the database.
     * @param array<int|string, int|string|bool> $options Connection options.
     * @throws mysqli_sql_exception
     */
    public function __construct(
        string $db_name,
        ?string $user = null,
        ?string $password = null,
        string $host = self::DEFAULT_HOST,
        int $port = self::DEFAULT_PORT,
        ?string $unix_socket = null,
        string $charset = self::DEFAULT_CHARSET,
        array $options = []
    ) {
        static $report_configured = false;
        if (!$report_configured) {
            mysqli_report(\MYSQLI_REPORT_ERROR | \MYSQLI_REPORT_STRICT);
            $report_configured = true;
        }

        parent::__construct();

        if (isset($options[\MYSQLI_SET_CHARSET_NAME])) {
            unset($options[\MYSQLI_SET_CHARSET_NAME]);
        }

        foreach ($options as $option => $value) {
            if (\is_bool($value)) {
                $value = (int) $value;
            }

            if (!$this->options((int) $option, $value)) {
                throw new mysqli_sql_exception(
                    sprintf('Unable to set MySQLi option "%s".', (string) $option),
                    500
                );
            }
        }

        if (!$this->real_connect($host, $user, $password, $db_name, $port, $unix_socket)) {
            throw new mysqli_sql_exception(
                sprintf('MySQLi connection error (%d): %s', $this->connect_errno, (string) $this->connect_error),
                $this->connect_errno ?: 500
            );
        }

        $charset = trim($charset);
        if ('' === $charset) {
            $charset = self::DEFAULT_CHARSET;
        }

        if (!$this->set_charset($charset)) {
            throw new mysqli_sql_exception(
                sprintf('Error loading charset "%s": %s', $charset, (string) $this->error),
                $this->errno ?: 500
            );
        }
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param  Closure(mysqli): mixed $callback
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

        if ($this->begin_transaction() === false) {
            throw new RuntimeException('Failed to start transaction with mysqli.');
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

        if ($this->commit() === false) {
            throw new RuntimeException('Could not commit with mysqli.');
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

        if ($this->rollback() === false) {
            throw new RuntimeException('Could not rollback with mysqli.');
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
        $driver = new mysqli_driver();
        if (($driver->report_mode & \MYSQLI_REPORT_STRICT) !== \MYSQLI_REPORT_STRICT) {
            throw new RuntimeException(
                'MySQLi error mode is not set to throw exceptions. '
                    . 'Please set MYSQLI_REPORT_STRICT.'
            );
        }
    }
}
