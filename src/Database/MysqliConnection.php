<?php

declare(strict_types=1);

namespace rguezque\Database;

use Closure;
use InvalidArgumentException;
use LogicException;
use mysqli;
use mysqli_driver;
use mysqli_sql_exception;
use rguezque\Contract\ConnectionInterface;
use RuntimeException;
use Throwable;

final class MysqliConnection extends mysqli implements ConnectionInterface
{
    public const PARAM_NULL = 'null';
    public const PARAM_INT = 'int';
    public const PARAM_FLOAT = 'float';
    public const PARAM_STR = 'string';
    public const PARAM_BLOB = 'blob';
    public const PARAM_BOOL = 'bool';

    /** @var string Default charset */
    private const DEFAULT_CHARSET = 'utf8mb4';

    /** @var string Default host */
    private const DEFAULT_HOST = 'localhost';

    /** @var int Default port */
    private const DEFAULT_PORT = 3306;

    /** @var bool Indicates whether this instance has already started a transaction. */
    private bool $in_transaction = false;

    /** @var bool|null Cached result of error mode check */
    private ?bool $error_mode_verified = null;

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
     * @throws InvalidArgumentException
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

        self::validateIdentifier($db_name, 'db_name');

        $charset = trim($charset);
        if ('' === $charset) {
            $charset = self::DEFAULT_CHARSET;
        }
        self::validateIdentifier($charset, 'charset');

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

        if (!$this->set_charset($charset)) {
            throw new mysqli_sql_exception(
                sprintf('Error loading charset "%s": %s', $charset, (string) $this->error),
                $this->errno ?: 500
            );
        }
    }

    /**
     * Insert a row into a table.
     *
     * @param string $table The table name.
     * @param array<string, mixed> $data Column names and values to insert.
     * @param array<string, string> $types Optional MysqliConnection parameter types keyed by column name.
     * @return int The number of affected rows.
     * @throws InvalidArgumentException If a table, column, or parameter type is invalid.
     * @throws RuntimeException If the statement cannot be prepared or executed.
     */
    public function insert(string $table, array $data, array $types = []): int
    {
        self::validateTable($table);
        if ($data === []) {
            throw new InvalidArgumentException('At least one column must be provided for insert.');
        }

        $columns = [];
        $values = [];
        foreach ($data as $column => $value) {
            self::validateColumn($column);
            $columns[] = sprintf('`%s`', $column);
            $values[] = $value;
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        return $this->executeStatement($sql, $values, array_keys($data), $types, array_keys($data), 'insert');
    }

    /**
     * Update rows in a table matching the given criteria.
     *
     * An empty criteria array updates every row in the table, as with Doctrine DBAL.
     *
     * @param string $table The table name.
     * @param array<string, mixed> $data Column names and values to update.
     * @param array<string, mixed> $criteria Column names and values used to match rows.
     * @param array<string, string> $types Optional MysqliConnection parameter types keyed by column name.
     * @return int The number of affected rows.
     * @throws InvalidArgumentException If a table, column, or parameter type is invalid.
     * @throws RuntimeException If the statement cannot be prepared or executed.
     */
    public function update(string $table, array $data, array $criteria, array $types = []): int
    {
        self::validateTable($table);
        if ($data === []) {
            throw new InvalidArgumentException('At least one column must be provided for update.');
        }

        $set_clauses = [];
        $where_clauses = [];
        $values = [];
        $parameter_columns = [];
        foreach ($data as $column => $value) {
            self::validateColumn($column);
            $set_clauses[] = sprintf('`%s` = ?', $column);
            $values[] = $value;
            $parameter_columns[] = $column;
        }

        foreach ($criteria as $column => $value) {
            self::validateColumn($column);
            if ($value === null) {
                $where_clauses[] = sprintf('`%s` IS NULL', $column);
                continue;
            }

            $where_clauses[] = sprintf('`%s` = ?', $column);
            $values[] = $value;
            $parameter_columns[] = $column;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s%s',
            $table,
            implode(', ', $set_clauses),
            $where_clauses === [] ? '' : ' WHERE ' . implode(' AND ', $where_clauses)
        );

        return $this->executeStatement(
            $sql,
            $values,
            $parameter_columns,
            $types,
            array_merge(array_keys($data), array_keys($criteria)),
            'update'
        );
    }

    /**
     * Delete rows from a table matching the given criteria.
     *
     * An empty criteria array deletes every row in the table, as with Doctrine DBAL.
     *
     * @param string $table The table name.
     * @param array<string, mixed> $criteria Column names and values used to match rows.
     * @param array<string, string> $types Optional MysqliConnection parameter types keyed by column name.
     * @return int The number of affected rows.
     * @throws InvalidArgumentException If a table, column, or parameter type is invalid.
     * @throws RuntimeException If the statement cannot be prepared or executed.
     */
    public function delete(string $table, array $criteria, array $types = []): int
    {
        self::validateTable($table);

        $where_clauses = [];
        $values = [];
        $parameter_columns = [];
        foreach ($criteria as $column => $value) {
            self::validateColumn($column);
            if ($value === null) {
                $where_clauses[] = sprintf('`%s` IS NULL', $column);
                continue;
            }

            $where_clauses[] = sprintf('`%s` = ?', $column);
            $values[] = $value;
            $parameter_columns[] = $column;
        }

        $sql = sprintf(
            'DELETE FROM `%s`%s',
            $table,
            $where_clauses === [] ? '' : ' WHERE ' . implode(' AND ', $where_clauses)
        );

        return $this->executeStatement(
            $sql,
            $values,
            $parameter_columns,
            $types,
            array_keys($criteria),
            'delete'
        );
    }

    /**
     * Execute a callback within a transaction.
     *
     * @param  Closure(ConnectionInterface): mixed $callback
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
     *
     * The result is cached per instance to avoid instantiating mysqli_driver
     * on every transactional call.
     */
    private function checkErrorModeEnabled(): void
    {
        if ($this->error_mode_verified === true) {
            return;
        }

        $driver = new mysqli_driver();
        if (($driver->report_mode & \MYSQLI_REPORT_STRICT) !== \MYSQLI_REPORT_STRICT) {
            throw new RuntimeException(
                'MySQLi error mode is not set to throw exceptions. '
                    . 'Please set MYSQLI_REPORT_STRICT.'
            );
        }

        $this->error_mode_verified = true;
    }

    /**
     * Prepare and execute a statement with values bound according to MySQLi parameter types.
     *
     * @param list<mixed> $values Values to bind, in placeholder order.
     * @param list<string> $parameter_columns Column name for each value.
     * @param array<string, string> $types Explicit MysqliConnection parameter types keyed by column.
     * @param list<string> $valid_columns Columns that may have explicit parameter types.
     */
    private function executeStatement(
        string $sql,
        array $values,
        array $parameter_columns,
        array $types,
        array $valid_columns,
        string $operation
    ): int {
        $valid_types = [
            self::PARAM_NULL,
            self::PARAM_INT,
            self::PARAM_FLOAT,
            self::PARAM_STR,
            self::PARAM_BLOB,
            self::PARAM_BOOL,
        ];
        foreach ($types as $column => $type) {
            if (!is_string($column) || !in_array($column, $valid_columns, true)) {
                throw new InvalidArgumentException(
                    sprintf('A parameter type was provided for an unknown column "%s".', (string) $column)
                );
            }
            if (!in_array($type, $valid_types, true)) {
                throw new InvalidArgumentException(
                    sprintf('The parameter type for column "%s" is not a supported MySQLi parameter type.', $column)
                );
            }
        }

        $statement = $this->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException(sprintf('Failed to prepare %s statement with MySQLi.', $operation));
        }

        try {
            if ($values !== []) {
                $bind_types = '';
                foreach ($values as $index => $value) {
                    $column = $parameter_columns[$index];
                    $type = $types[$column] ?? match (true) {
                        $value === null => self::PARAM_NULL,
                        is_bool($value) => self::PARAM_BOOL,
                        is_int($value) => self::PARAM_INT,
                        is_float($value) => self::PARAM_FLOAT,
                        default => self::PARAM_STR,
                    };
                    $bind_types .= match ($type) {
                        self::PARAM_INT, self::PARAM_BOOL => 'i',
                        self::PARAM_FLOAT => 'd',
                        self::PARAM_BLOB => 'b',
                        default => 's',
                    };
                }

                $bind_parameters = [$bind_types];
                foreach ($values as $index => &$value) {
                    $bind_parameters[] = &$value;
                }
                unset($value);

                if (!call_user_func_array([$statement, 'bind_param'], $bind_parameters)) {
                    throw new RuntimeException(sprintf('Failed to bind %s parameters with MySQLi.', $operation));
                }
            }

            if (!$statement->execute()) {
                throw new RuntimeException(sprintf('Failed to execute %s statement with MySQLi.', $operation));
            }

            return $statement->affected_rows;
        } finally {
            $statement->close();
        }
    }

    /**
     * Validate a table identifier.
     */
    private static function validateTable(string $table): void
    {
        if ($table === '') {
            throw new InvalidArgumentException('The table name cannot be empty.');
        }
        self::validateIdentifier($table, 'table');
    }

    /**
     * Validate a column identifier.
     *
     * @param mixed $column
     */
    private static function validateColumn(mixed $column): void
    {
        if (!is_string($column) || $column === '') {
            throw new InvalidArgumentException('Column names must be non-empty strings.');
        }
        self::validateIdentifier($column, 'column');
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
