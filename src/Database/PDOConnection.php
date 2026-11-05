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
     * Insert a row into a table.
     *
     * @param string $table The table name.
     * @param array<string, mixed> $data Column names and values to insert.
     * @param array<string, int> $types Optional PDO parameter types keyed by column name.
     * @return int The number of affected rows.
     * @throws InvalidArgumentException If a table, column, or parameter type is invalid.
     * @throws RuntimeException If the statement cannot be prepared or executed.
     */
    public function insert(string $table, array $data, array $types = []): int
    {
        if ($table === '') {
            throw new InvalidArgumentException('The table name cannot be empty.');
        }
        self::validateIdentifier($table, 'table');

        if ($data === []) {
            throw new InvalidArgumentException('At least one column must be provided for insert.');
        }

        $columns = [];
        $column_names = [];
        $placeholders = [];
        $values = [];
        foreach ($data as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('Column names must be non-empty strings.');
            }
            self::validateIdentifier($column, 'column');

            $columns[] = sprintf('`%s`', $column);
            $column_names[] = $column;
            $placeholders[] = '?';
            $values[] = $value;
        }

        foreach ($types as $column => $type) {
            if (!is_string($column) || !array_key_exists($column, $data)) {
                throw new InvalidArgumentException(
                    sprintf('A parameter type was provided for an unknown column "%s".', (string) $column)
                );
            }
            if (!in_array(
                $type,
                [PDO::PARAM_NULL, PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_LOB, PDO::PARAM_BOOL],
                true
            )) {
                throw new InvalidArgumentException(
                    sprintf('The parameter type for column "%s" is not a supported PDO parameter type.', $column)
                );
            }
        }

        $quoted_table = sprintf('`%s`', $table);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $quoted_table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $statement = $this->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Failed to prepare insert statement with PDO.');
        }

        foreach ($values as $index => $value) {
            $column = $column_names[$index];
            $type = $types[$column] ?? match (true) {
                $value === null => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };

            if ($statement->bindValue($index + 1, $value, $type) === false) {
                throw new RuntimeException('Failed to bind insert parameter with PDO.');
            }
        }

        if ($statement->execute() === false) {
            throw new RuntimeException('Failed to execute insert statement with PDO.');
        }

        return $statement->rowCount();
    }

    /**
     * Update rows in a table matching the given criteria.
     *
     * An empty criteria array updates every row in the table, as with Doctrine DBAL.
     *
     * @param string $table The table name.
     * @param array<string, mixed> $data Column names and values to update.
     * @param array<string, mixed> $criteria Column names and values used to match rows.
     * @param array<string, int> $types Optional PDO parameter types keyed by column name.
     * @return int The number of affected rows.
     * @throws InvalidArgumentException If a table, column, or parameter type is invalid.
     * @throws RuntimeException If the statement cannot be prepared or executed.
     */
    public function update(string $table, array $data, array $criteria, array $types = []): int
    {
        if ($table === '') {
            throw new InvalidArgumentException('The table name cannot be empty.');
        }
        self::validateIdentifier($table, 'table');

        if ($data === []) {
            throw new InvalidArgumentException('At least one column must be provided for update.');
        }

        $set_clauses = [];
        $where_clauses = [];
        $values = [];
        $parameter_columns = [];

        foreach ($data as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('Column names must be non-empty strings.');
            }
            self::validateIdentifier($column, 'column');

            $set_clauses[] = sprintf('`%s` = ?', $column);
            $values[] = $value;
            $parameter_columns[] = $column;
        }

        foreach ($criteria as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('Criteria column names must be non-empty strings.');
            }
            self::validateIdentifier($column, 'column');

            if ($value === null) {
                $where_clauses[] = sprintf('`%s` IS NULL', $column);
                continue;
            }

            $where_clauses[] = sprintf('`%s` = ?', $column);
            $values[] = $value;
            $parameter_columns[] = $column;
        }

        $valid_types = [PDO::PARAM_NULL, PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_LOB, PDO::PARAM_BOOL];
        foreach ($types as $column => $type) {
            if (!is_string($column) || (!array_key_exists($column, $data) && !array_key_exists($column, $criteria))) {
                throw new InvalidArgumentException(
                    sprintf('A parameter type was provided for an unknown column "%s".', (string) $column)
                );
            }
            if (!in_array($type, $valid_types, true)) {
                throw new InvalidArgumentException(
                    sprintf('The parameter type for column "%s" is not a supported PDO parameter type.', $column)
                );
            }
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s%s',
            $table,
            implode(', ', $set_clauses),
            $where_clauses === [] ? '' : ' WHERE ' . implode(' AND ', $where_clauses)
        );

        $statement = $this->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Failed to prepare update statement with PDO.');
        }

        foreach ($values as $index => $value) {
            $column = $parameter_columns[$index];
            $type = $types[$column] ?? match (true) {
                $value === null => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };

            if ($statement->bindValue($index + 1, $value, $type) === false) {
                throw new RuntimeException('Failed to bind update parameter with PDO.');
            }
        }

        if ($statement->execute() === false) {
            throw new RuntimeException('Failed to execute update statement with PDO.');
        }

        return $statement->rowCount();
    }

    /**
     * Delete rows from a table matching the given criteria.
     *
     * An empty criteria array deletes every row in the table, as with Doctrine DBAL.
     *
     * @param string $table The table name.
     * @param array<string, mixed> $criteria Column names and values used to match rows.
     * @param array<string, int> $types Optional PDO parameter types keyed by column name.
     * @return int The number of affected rows.
     * @throws InvalidArgumentException If a table, column, or parameter type is invalid.
     * @throws RuntimeException If the statement cannot be prepared or executed.
     */
    public function delete(string $table, array $criteria, array $types = []): int
    {
        if ($table === '') {
            throw new InvalidArgumentException('The table name cannot be empty.');
        }
        self::validateIdentifier($table, 'table');

        $where_clauses = [];
        $values = [];
        $parameter_columns = [];
        foreach ($criteria as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('Criteria column names must be non-empty strings.');
            }
            self::validateIdentifier($column, 'column');

            if ($value === null) {
                $where_clauses[] = sprintf('`%s` IS NULL', $column);
                continue;
            }

            $where_clauses[] = sprintf('`%s` = ?', $column);
            $values[] = $value;
            $parameter_columns[] = $column;
        }

        $valid_types = [PDO::PARAM_NULL, PDO::PARAM_INT, PDO::PARAM_STR, PDO::PARAM_LOB, PDO::PARAM_BOOL];
        foreach ($types as $column => $type) {
            if (!is_string($column) || !array_key_exists($column, $criteria)) {
                throw new InvalidArgumentException(
                    sprintf('A parameter type was provided for an unknown column "%s".', (string) $column)
                );
            }
            if (!in_array($type, $valid_types, true)) {
                throw new InvalidArgumentException(
                    sprintf('The parameter type for column "%s" is not a supported PDO parameter type.', $column)
                );
            }
        }

        $sql = sprintf(
            'DELETE FROM `%s`%s',
            $table,
            $where_clauses === [] ? '' : ' WHERE ' . implode(' AND ', $where_clauses)
        );

        $statement = $this->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Failed to prepare delete statement with PDO.');
        }

        foreach ($values as $index => $value) {
            $column = $parameter_columns[$index];
            $type = $types[$column] ?? match (true) {
                $value === null => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };

            if ($statement->bindValue($index + 1, $value, $type) === false) {
                throw new RuntimeException('Failed to bind delete parameter with PDO.');
            }
        }

        if ($statement->execute() === false) {
            throw new RuntimeException('Failed to execute delete statement with PDO.');
        }

        return $statement->rowCount();
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
