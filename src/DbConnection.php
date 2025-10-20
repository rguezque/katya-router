<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use mysqli;
use mysqli_sql_exception;
use PDO;
use PDOException;
use rguezque\Exceptions\PermissionException;

use function rguezque\functions\env;

/**
 * Represents a MySQL/SQLite connection
 * 
 * This class provides methods to establish a connection to a MySQL database
 * using either the PDO or mysqli driver. It supports connection parameters
 * such as host, port, database name, charset, user, password, and socket.
 * 
 * @method PDO|mysqli getConnection(array $params) Return a Singleton MySQL connection.
 * @method PDO|mysqli autoConnect() Return a MySQL connection from stored params into .env file (using dotenv library).
 * @method PDO|mysqli create(array $params) Create a new PDO(mysql|sqlite) or mysqli connection.
 * @method array dsnParser(string $url) This method extracts the components of a database URL and returns them as an associative array.
 */
class DbConnection {

    /** @var PDO|mysqli|null */
    private static $connection = null;

    /** @var array */
    private static array $default_pdo_options = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];

    /** @var string */
    private static string $charset = 'utf8';

    /** @var array<string> */
    private static array $supported_drivers = ['pdomysql', 'mysqli', 'pdo_sqlite'];

    /**
     * Return a Singleton PDO (mysql|sqlite) or mysqli connection.
     * @param array $params
     * @return PDO|mysqli
     * @throws Throwable if the connection fails.
     */
    public static function getConnection(array $params): PDO|mysqli {
        return self::$connection ??= self::create($params);
    }

    /**
     * Create a new PDO (mysql|sqlite) or mysqli connection.
     * @param array $params
     * @return PDO|mysqli
     * @throws InvalidArgumentException if the driver is not supported.
     */
    public static function create(array $params): PDO|mysqli {
        $driver = $params['driver'] ?? 'pdomysql';
        if (!in_array($driver, self::$supported_drivers, true)) {
            throw new InvalidArgumentException('Invalid driver: must be "pdomysql", "pdo_sqlite" or "mysqli".');
        }
        return match($driver) {
            'pdomysql'   => self::connectPDOMysql($params),
            'mysqli'     => self::connectMysqli($params),
            'pdo_sqlite' => self::connectPDOSqlite($params),
        };
    }

    /**
     * Return a MySQL connection from .env params (dotenv library).
     * @return PDO|mysqli
     * @throws Throwable if the connection fails.
     */
    public static function autoConnect(): PDO|mysqli {
        $params = [
            'driver'  => env('db.driver', 'pdomysql'),
            'host'    => env('db.host', '127.0.0.1'),
            'port'    => env('db.host', 3306, CAST_INT),
            'db_name' => env('db.name', ''),
            'charset' => env('db.charset', self::$charset),
            'user'    => env('db.user', ''),
            'pass'    => env('db.pass', ''),
            'socket'  => env('db.socket')
        ];
        return self::getConnection($params);
    }

    /**
     * Parse a database URL into an associative array.
     * @param string $url
     * @return array
     * @throws InvalidArgumentException if the scheme is not supported.
     */
    public static function dsnParser(string $url): array {
        $dsn = parse_url($url);
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
     * @param array $params
     * @return PDO
     * @throws PDOException if the connection fails.
     */
    private static function connectPDOMysql(array $params): PDO {
        $dsn = isset($params['socket']) && trim((string)$params['socket']) !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s;',
                $params['socket'], $params['db_name'] ?? '', $params['charset'] ?? self::$charset)
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s;',
                $params['host'] ?? '127.0.0.1', (int)($params['port'] ?? 3306), $params['db_name'] ?? '', $params['charset'] ?? self::$charset);

        $options = array_replace(self::$default_pdo_options, $params['options'] ?? []);
        try {
            return new PDO($dsn, $params['user'] ?? '', $params['pass'] ?? '', $options);
        } catch(PDOException $e) {
            throw new PDOException('Failed to connect to MySQL with PDO: ' . $e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Establish a connection to a MySQL database using mysqli.
     * @param array $params
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
        if ($mysqli->connect_errno) {
            throw new mysqli_sql_exception('Failed to connect to MySQL with mysqli: ' . $mysqli->connect_error);
        }
        $charset = $params['charset'] ?? self::$charset;
        if (!$mysqli->set_charset($charset)) {
            throw new mysqli_sql_exception("Error loading charset $charset: " . $mysqli->error);
        }
        return $mysqli;
    }

    /**
     * Establish a connection to a SQLite database using PDO. If the file don't exist it will try to create it.
     * @param array $params
     * @return PDO
     * @throws PDOException if the connection fails.
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
        try {
            $conn = new PDO($dsn, null, null, $options);
            $conn->exec("PRAGMA encoding = '" . $charset . "';");
            $conn->exec("PRAGMA foreign_keys = " . ($fk_support ? "ON" : "OFF") . ";");
            return $conn;
        } catch (PDOException $e) {
            throw new PDOException("Error connecting to SQLite: " . $e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Try to create the SQLite database file and its directory if they do not exist.
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
        if (!chmod($db_file, 0655)) {
            throw new PermissionException("Failed to set permissions on SQLite file: $db_file");
        }
    }

    /**
     * Get the list of supported drivers.
     * @return array<string>
     */
    public static function getSupportedDrivers(): array {
        return self::$supported_drivers;
    }
}