<?php

declare(strict_types=1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\Database;

use InvalidArgumentException;
use mysqli_sql_exception;
use PDO;
use PDOException;
use rguezque\Contract\ConnectionInterface;
use rguezque\Exception\MissingArgumentException;

use function rguezque\functions\{
    env,
    normalize_port,
    trimmed_string_or_default,
    trimmed_string_or_null,
};

/**
 * Represents a database connection factory that can create PDO or MySQLi connections based on provided parameters or environment variables.
 * 
 * This class provides methods to create a new connection with specified parameters or automatically connect using environment variables. It also includes methods to retrieve supported drivers and normalize connection parameters.
 * 
 * @method static ConnectionInterface create(array<string, mixed> $params) Create a new PDO or MySQLi connection based on provided parameters.
 * @method static ConnectionInterface autoConnect(array<string, mixed> $driver_options = []) Automatically connect to a database using environment variables, with optional driver-specific options.
 * @method static array<string> getSupportedDrivers() Get the list of supported canonical drivers.
 */
final class Connection
{
    /** @var string */
    public const DEFAULT_CHARSET = 'utf8mb4';

    /** @var int */
    public const DEFAULT_PORT = 3306;

    /** @var string */
    public const DEFAULT_HOST = 'localhost';

    /** @var array<string> */
    private const SUPPORTED_DRIVERS = [
        'pdomysql',
        'mysqli',
    ];

    /** @var ConnectionInterface|null */
    private static ConnectionInterface|null $auto_connection = null;

    private function __construct() {}

    /**
     * Create a new PDO MySQL or MySQLi connection. If a unix socket was defined, it is given priority in the connection.
     *
     * @param array<string, mixed> $params Parameters for the connection.
     * @return ConnectionInterface
     * @throws MissingArgumentException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws mysqli_sql_exception
     */
    public static function create(array $params): ConnectionInterface
    {
        $params = self::normalizeParams($params);

        return match ($params['driver']) {
            'pdomysql' => self::connectPDOMysql($params),
            'mysqli'   => self::connectMysqli($params),
        };
    }

    /**
     * Return a MySQL connection from environment params.
     *
     * If DB_URL or DATABASE_URL is present, the URL is parsed and cached.
     *
     * @param array<string, mixed> $driver_options Optional driver-specific options.
     * @return ConnectionInterface
     */
    public static function autoConnect(array $driver_options = []): ConnectionInterface
    {
        if (self::$auto_connection !== null) {
            return self::$auto_connection;
        }

        $url = env('DB_URL');

        if (!is_string($url) || trim($url) === '') {
            $url = env('DATABASE_URL');
        }

        if (is_string($url) && trim($url) !== '') {
            return self::$auto_connection = self::create((new DsnParser)->parse($url));
        }

        $params = [
            'driver'   => env('DB_DRIVER'),
            'host'     => env('DB_HOST'),
            'port'     => env('DB_PORT'),
            'db_name'  => env('DB_NAME', ''),
            'charset'  => env('DB_CHARSET'),
            'user'     => env('DB_USER', ''),
            'password' => env('DB_PASS', ''),
            'unix_socket'   => env('DB_SOCKET'),
            'options'  => $driver_options,
        ];

        return self::$auto_connection = self::create($params);
    }

    /**
     * Get the list of supported canonical drivers.
     *
     * @return array<string>
     */
    public static function getSupportedDrivers(): array
    {
        return self::SUPPORTED_DRIVERS;
    }

    /**
     * Establish a connection to a MySQL database using PDO.
     *
     * @param array<string, mixed> $params Parameters for the PDO connection.
     * @return PDOConnection
     * @throws PDOException
     */
    private static function connectPDOMysql(array $params): PDOConnection
    {
        $options = array_replace(
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ],
            $params['options']
        );

        return new PDOConnection(
            $params['db_name'],
            $params['host'],
            $params['port'],
            $params['unix_socket'],
            $params['user'],
            $params['password'],
            $params['charset'],
            $options
        );
    }

    /**
     * Establish a connection to a MySQL database using MySQLi. If a unix socket was defined, it is given priority in the connection.
     *
     * @param array<string, mixed> $params Parameters for the MySQLi connection.
     * @return MysqliConnection
     * @throws mysqli_sql_exception
     */
    private static function connectMysqli(array $params): MysqliConnection
    {
        return new MysqliConnection(
            $params['db_name'],
            $params['host'],
            $params['port'],
            $params['unix_socket'],
            $params['user'],
            $params['password'],
            $params['charset'],
            $params['options'],
        );
    }

    /**
     * Normalize and validate connection params.
     *
     * @param array<string, mixed> $params Parameters to normalize and validate.
     * @return array<string, mixed>
     * @throws MissingArgumentException
     * @throws InvalidArgumentException
     */
    private static function normalizeParams(array $params): array
    {
        $driver = trimmed_string_or_null($params['driver'] ?? '');

        if ($driver === null) {
            throw new MissingArgumentException(
                'Missing "driver" parameter. Must be "pdomysql" or "mysqli".',
                400
            );
        }

        $driver = strtolower($driver);

        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new InvalidArgumentException(
                'Invalid "driver", must be: "pdomysql" or "mysqli".',
                400
            );
        }

        $options = $params['options'] ?? [];

        if (!is_array($options)) {
            throw new InvalidArgumentException(
                'Invalid "options" parameter; it must be an array.',
                400
            );
        }

        $socket = trimmed_string_or_null($params['unix_socket'] ?? null);
        $host = trimmed_string_or_default($params['host'] ?? null, self::DEFAULT_HOST);

        // Evaluates whether a unix socket is used and that the "host" is "localhost" in the case of a mysqli connection
        if ($socket !== null && $driver === 'mysqli' && $host !== 'localhost') {
            throw new InvalidArgumentException('The "host" parameter must be "localhost" when a unix socket is used in mysqli connection.');
        }

        return [
            'driver'   => $driver,
            'host'     => $host,
            'port'     => normalize_port($params['port'] ?? null, self::DEFAULT_PORT),
            'db_name'  => trimmed_string_or_default($params['db_name'] ?? null, ''),
            'charset'  => trimmed_string_or_default($params['charset'] ?? null, self::DEFAULT_CHARSET),
            'user'     => trimmed_string_or_default($params['user'] ?? null, ''),
            'password' => trimmed_string_or_default($params['password'] ?? null, ''),
            'unix_socket'   => $socket,
            'options'  => $options,
        ];
    }
}
