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
use mysqli;
use mysqli_sql_exception;
use PDO;
use PDOException;
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
 * @method static PDO|mysqli create(array<string, mixed> $params) Create a new PDO or MySQLi connection based on provided parameters.
 * @method static PDO|mysqli autoConnect(array<string, mixed> $driver_options = []) Automatically connect to a database using environment variables, with optional driver-specific options.
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

    /** @var PDO|mysqli|null */
    private static PDO|mysqli|null $auto_connection = null;

    private function __construct() {}

    /**
     * Create a new PDO MySQL or MySQLi connection. If a unix socket was defined, it is given priority in the connection.
     *
     * @param array<string, mixed> $params Parameters for the connection.
     * @return PDO|mysqli
     * @throws MissingArgumentException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws mysqli_sql_exception
     */
    public static function create(array $params): PDO|mysqli
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
     * @return PDO|mysqli
     */
    public static function autoConnect(array $driver_options = []): PDO|mysqli
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
            'dbname'  => env('DB_NAME', ''),
            'charset'  => env('DB_CHARSET'),
            'user'     => env('DB_USER', ''),
            'password' => env('DB_PASS', ''),
            'socket'   => env('DB_SOCKET'),
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
     * @return PDO
     * @throws PDOException
     */
    private static function connectPDOMysql(array $params): PDO
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

        $dsn = $params['socket'] !== null
            ? sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=%s',
                $params['socket'],
                $params['dbname'],
                $params['charset']
            )
            : sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $params['host'],
                $params['port'],
                $params['dbname'],
                $params['charset']
            );

        return new PDO(
            $dsn,
            $params['user'],
            $params['password'],
            $options
        );
    }

    /**
     * Establish a connection to a MySQL database using MySQLi. If a unix socket was defined, it is given priority in the connection.
     *
     * @param array<string, mixed> $params Parameters for the MySQLi connection.
     * @return mysqli
     * @throws mysqli_sql_exception
     */
    private static function connectMysqli(array $params): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        // Previously normalized params, so charset is guaranteed to be a non-empty string.
        $charset = $params['charset'];

        if ([] !== $params['options']) {
            // If MYSQLI_SET_CHARSET_NAME is set in options, use it as charset 
            // and unset this option from the array to avoid conflicts with mysqli::set_charset.
            if(isset($params['options'][MYSQLI_SET_CHARSET_NAME])) {
                $charset = $params['options'][MYSQLI_SET_CHARSET_NAME];
                unset($params['options'][MYSQLI_SET_CHARSET_NAME]);
            }

            $mysqli = mysqli_init();
            
            foreach ($params['options'] as $option => $value) {
                $mysqli->options($option, $value);
            }

            $mysqli->real_connect(
                $params['host'],
                $params['user'],
                $params['password'],
                $params['dbname'],
                $params['port'],
                $params['socket']
            );
        } else {
            $mysqli = new mysqli(
                $params['host'],
                $params['user'],
                $params['password'],
                $params['dbname'],
                $params['port'],
                $params['socket']
            );
        }

        $mysqli->connect_errno && throw new mysqli_sql_exception(
            sprintf(
                'MySQLi connection error (%d): %s',
                $mysqli->connect_errno,
                $mysqli->connect_error
            ),
            500
        );

        // mysqli::set_charset is the standard, safe, and immediate method. 
        // It modifies the driver's character-escaping behavior. 
        // It is executed after opening the connection.
        if (!$mysqli->set_charset($charset)) {
            throw new mysqli_sql_exception(
                sprintf(
                    'Error loading charset "%s": %s',
                    $charset,
                    $mysqli->error
                ),
                500
            );
        }

        return $mysqli;
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

        $socket = trimmed_string_or_null($params['socket'] ?? null);
        // "host" is normalized in case a socket has been defined, since mysqli requires the host to be "localhost", while PDO ignores it completely
        $host = ($socket !== null && $driver === 'mysqli') ? self::DEFAULT_HOST : trimmed_string_or_default($params['host'] ?? null, self::DEFAULT_HOST);

        return [
            'driver'   => $driver,
            'host'     => $host,
            'port'     => normalize_port($params['port'] ?? null, self::DEFAULT_PORT),
            'dbname'  => trimmed_string_or_default($params['dbname'] ?? null, ''),
            'charset'  => trimmed_string_or_default($params['charset'] ?? null, self::DEFAULT_CHARSET),
            'user'     => trimmed_string_or_default($params['user'] ?? null, ''),
            'password' => trimmed_string_or_default($params['password'] ?? null, ''),
            'socket'   => $socket,
            'options'  => $options,
        ];
    }
}
