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
use Throwable;
use function rguezque\functions\{
    decode_component,
    normalize_host,
    normalize_path,
    normalize_port,
    trimmed_string_or_default,
    trimmed_string_or_null,
};

/**
 * Parses a database URL (DSN) into an associative array of connection parameters.
 */
final class DsnParser
{
    /** @var array<string, string> Aliases for database schemes */
    private const SCHEME_ALIASES = [
        'pdomysql'  => 'pdomysql',
        'pdo_mysql' => 'pdomysql',
        'mysql'     => 'pdomysql',
        'mysqli'    => 'mysqli',
    ];

    /** @var array<string, mixed> Parameters parsed from URL */
    private array $params;

    /**
     * Parse a database URL into an associative array.
     *
     * @param string $url
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('Database URL cannot be empty.', 400);
        }

        $dsn = $this->parseDsn($url);
        if ($dsn === false) {
            throw new InvalidArgumentException('Malformed database URL.', 400);
        }

        $scheme = strtolower(trim($dsn['scheme'] ?? 'pdomysql'));
        if (!isset(self::SCHEME_ALIASES[$scheme])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid "scheme" in database URL, must be one of: %s.',
                    implode(', ', array_keys(self::SCHEME_ALIASES))
                ),
                400
            );
        }

        $segments = [];
        if (isset($dsn['query'])) {
            parse_str($dsn['query'], $segments);
        }

        $params = [
            'driver'      => self::SCHEME_ALIASES[$scheme],
            'host'        => normalize_host($dsn['host'] ?? null, Connection::DEFAULT_HOST),
            'port'        => normalize_port($dsn['port'] ?? null, Connection::DEFAULT_PORT),
            'db_name'     => normalize_path($dsn['path'] ?? null),
            'charset'     => trimmed_string_or_default($segments['charset'] ?? null, Connection::DEFAULT_CHARSET),
            'user'        => decode_component($dsn['user'] ?? ''),
            'password'    => decode_component($dsn['pass'] ?? ''), // Do NOT trim passwords
            'unix_socket' => trimmed_string_or_null($segments['unix_socket'] ?? null),
        ];

        return $this->params;
    }

    /**
     * Parses the URL using `\Uri\Rfc3986\Uri` when available.
     *
     * If the class does not exist, does not expose the expected API, or fails
     * to parse the URL, it falls back to PHP's native `parse_url()`.
     *
     * @param string $url The URL to parse
     * @return array<string, mixed>|false
     */
    private function parseDsn(string $url)
    {
        $uri_class = \Uri\Rfc3986\Uri::class;
        if (class_exists($uri_class)) {
            $required_methods = [
                'getScheme',
                'getHost',
                'getPort',
                'getPath',
                'getQuery',
                'getUserInfo',
            ];
            $is_supported = true;
            foreach ($required_methods as $method) {
                if (!method_exists($uri_class, $method)) {
                    $is_supported = false;
                    break;
                }
            }

            if ($is_supported) {
                try {
                    /** @var object $uri */
                    $uri = new $uri_class($url);
                    $dsn = [];

                    $scheme = (string) $uri->getScheme();
                    if ('' !== $scheme) {
                        $dsn['scheme'] = $scheme;
                    }

                    $host = (string) $uri->getHost();
                    if ('' !== $host) {
                        $dsn['host'] = $host;
                    }

                    $port = $uri->getPort();
                    if (null !== $port) {
                        $dsn['port'] = (int) $port;
                    }

                    $path = (string) $uri->getPath();
                    if ('' !== $path) {
                        $dsn['path'] = $path;
                    }

                    $query = (string) $uri->getQuery();
                    if ('' !== $query) {
                        $dsn['query'] = $query;
                    }

                    $user_info = (string) $uri->getUserInfo();
                    if ('' !== $user_info) {
                        $parts = explode(':', $user_info, 2);
                        $user = $parts[0];
                        $pass = $parts[1] ?? '';
                        if ('' !== $user) {
                            $dsn['user'] = $user;
                        }
                        if ('' !== $pass || false !== strpos($user_info, ':')) {
                            $dsn['pass'] = $pass;
                        }
                    }

                    return $dsn;
                } catch (Throwable $exception) {
                    // If parsing with \Uri\Rfc3986\Uri fails, fallback to parse_url().
                }
            }
        }

        return parse_url($url);
    }
}
