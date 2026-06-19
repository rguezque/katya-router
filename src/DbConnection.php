<?php

declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use mysqli_sql_exception;
use mysqli;
use PDO;
use PDOException;
use rguezque\Exceptions\PermissionException;
use Throwable;
use function rguezque\functions\env;

/**
 * Represents a MySQL/SQLite connection factory and registry.
 *
 * This class provides methods to establish a connection to a MySQL/SQLite database
 * using either the PDO or mysqli driver. It acts as a Multiton/Registry to allow 
 * multiple simultaneous connections to different databases/drivers.
 */
class DbConnection {
    /** @var array<string, PDO|mysqli> Registry of connections */
    private static array $connections = [];

    /** @var array Default options */
    private static array $default_pdo_options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];

    /** @var string Default charset for connection (utf8mb4 is the real UTF-8) */
    private static string $charset = 'utf8mb4';

    /** @var array<string> Supported drivers for connection */
    private static array $supported_drivers = ['pdomysql', 'mysqli', 'pdo_sqlite'];

    /**
     * Return a registered PDO (mysql|sqlite) or mysqli connection.
     * If it doesn't exist, it creates and registers it.
     *
     * @param array $params
     * @return PDO|mysqli
     * @throws Throwable if the connection fails.
     */
    public static function getConnection(array $params): PDO|mysqli {
        $key = self::generateConnectionKey($params);

        if (!isset(self::$connections[$key])) {
            self::$connections[$key] = self::create($params);
        }

        return self::$connections[$key];
    }

    /**
     * Create a new PDO (mysql|sqlite) or mysqli connection.
     *
     * @param array $params
     * @return PDO|mysqli
     * @throws InvalidArgumentException if the driver is not supported.
     */
    public static function create(array $params): PDO|mysqli {
        $driver = $params['driver'] ?? 'pdomysql';

        if (!in_array($driver, self::$supported_drivers, true)) {
            throw new InvalidArgumentException('Invalid driver, must be: "pdomysql", "pdo_sqlite" or "mysqli".');
        }

        return match ($driver) {
            'pdomysql'   => self::connectPDOMysql($params),
            'mysqli'     => self::connectMysqli($params),
            'pdo_sqlite' => self::connectPDOSqlite($params),
        };
    }

    /**
     * Return a MySQL connection from .env params (dotenv library).
     *
     * @return PDO|mysqli
     * @throws Throwable if the connection fails.
     */
    public static function autoConnect(): PDO|mysqli {
        $params = [
            'driver'  => env('DB_DRIVER', 'pdomysql'),
            'host'    => env('DB_HOST', '127.0.0.1'),
            'port'    => env('DB_PORT', 3306),
            'db_name' => env('DB_NAME', ''),
            'charset' => env('DB_CHARSET', self::$charset),
            'user'    => env('DB_USER', ''),
            'pass'    => env('DB_PASS', ''),
            'socket'  => env('DB_SOCKET')
        ];

        return self::getConnection($params);
    }

    /**
     * Parse a database URL into an associative array.
     * 
     * @param string $url
     * @return array
     * @throws InvalidArgumentException if the URL is malformed or scheme is not supported.
     */
    public static function dsnParser(string $url): array {
        $dsn = parse_url($url);

        if ($dsn === false) {
            throw new InvalidArgumentException('Malformed database URL.');
        }

        if (isset($dsn['scheme']) && !in_array($dsn['scheme'], ['pdomysql', 'mysqli'], true)) {
            throw new InvalidArgumentException('Invalid "scheme" in database URL, must be "pdomysql" or "mysqli".');
        }

        $segments = [];
        if (isset($dsn['query'])) {
            parse_str($dsn['query'], $segments);
        }

        return [
            'driver'  => $dsn['scheme'] ?? 'pdomysql',
            'host'    => $dsn['host'] ?? '127.0.0.1',
            'port'    => (int)($dsn['port'] ?? 3306),
            'db_name' => isset($dsn['path']) ? trim($dsn['path'], '/\\') : '',
            'charset' => $segments['charset'] ?? self::$charset,
            'user'    => $dsn['user'] ?? '',
            'pass'    => $dsn['pass'] ?? '',
            'socket'  => $segments['socket'] ?? null
        ];
    }

    /**
     * Establish a connection to a MySQL database using PDO.
     *
     * @param array $params Connection params
     * @return PDO
     * @throws PDOException if the connection fails.
     */
    private static function connectPDOMysql(array $params): PDO {
        $charset = $params['charset'] ?? self::$charset;

        $dsn = isset($params['socket']) && trim((string)$params['socket']) !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s;', $params['socket'], $params['db_name'] ?? '', $charset)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s;', $params['host'] ?? '127.0.0.1', (int)($params['port'] ?? 3306), $params['db_name'] ?? '', $charset);

        $options = array_replace(self::$default_pdo_options, $params['options'] ?? []);

        return new PDO($dsn, $params['user'] ?? '', $params['pass'] ?? '', $options);
    }

    /**
     * Establish a connection to a MySQL database using mysqli.
     *
     * @param array $params Connection params
     * @return mysqli
     * @throws mysqli_sql_exception if the connection fails.
     */
    private static function connectMysqli(array $params): mysqli {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $mysqli = new mysqli(
            $params['host'] ?? '127.0.0.1',
            $params['user'] ?? '',
            $params['pass'] ?? '',
            $params['db_name'] ?? '',
            (int)($params['port'] ?? 3306),
            $params['socket'] ?? null
        );

        // Note: The connect_errno check is removed because MYSQLI_REPORT_STRICT 
        // already throws a mysqli_sql_exception on failure.

        $charset = $params['charset'] ?? self::$charset;
        if (!$mysqli->set_charset($charset)) {
            throw new mysqli_sql_exception("Error loading charset $charset: " . $mysqli->error);
        }

        return $mysqli;
    }

    /**
     * Establish a connection to a SQLite database using PDO.
     *
     * @param array $params Connection params
     * @return PDO
     * @throws PDOException|PermissionException if the connection or file creation fails.
     */
    private static function connectPDOSqlite(array $params): PDO {
        $db_file = $params['db_file'] ?? ':memory:';

        if ($db_file !== ':memory:' && !file_exists($db_file)) {
            self::tryCreateSqlite($db_file);
        }

        $dsn = 'sqlite:' . $db_file;
        $charset = $params['charset'] ?? self::$charset;
        $options = array_replace(self::$default_pdo_options, $params['options'] ?? []);
        $fk_support = $params['fk_support'] ?? false;

        $conn = new PDO($dsn, null, null, $options);
        $conn->exec("PRAGMA encoding = '" . $charset . "';");
        $conn->exec("PRAGMA foreign_keys = " . ($fk_support ? "ON" : "OFF") . ";");

        return $conn;
    }

    /**
     * Try to create the SQLite database file and its directory if they do not exist.
     *
     * @param string $db_file
     * @throws PermissionException if the directory or file cannot be created.
     */
    private static function tryCreateSqlite(string $db_file): void {
        $dir = dirname($db_file);

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new PermissionException("Failed to create directory for SQLite: $dir");
        }

        if (!touch($db_file)) {
            throw new PermissionException("Failed to create SQLite file: $db_file");
        }

        // 0644 is the correct permission for a database file (Read/Write for owner, Read for others)
        if (!chmod($db_file, 0644)) {
            throw new PermissionException("Failed to set permissions on SQLite file: $db_file");
        }
    }

    /**
     * Get the list of supported drivers.
     *
     * @return array<string>
     */
    public static function getSupportedDrivers(): array {
        return self::$supported_drivers;
    }

    /**
     * Generate a unique key for the connection registry based on params.
     *
     * @param array $params
     * @return string
     */
    private static function generateConnectionKey(array $params): string {
        $driver = $params['driver'] ?? 'pdomysql';
        $db = $params['db_name'] ?? $params['db_file'] ?? 'default';
        return $driver . '_' . $db;
    }
}
