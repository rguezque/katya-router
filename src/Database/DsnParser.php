<?php

declare(strict_types=1);

namespace rguezque\Database;

use InvalidArgumentException;

use function rguezque\functions\{
    decode_component,
    normalize_host,
    normalize_path,
    normalize_port,
    trimmed_string_or_default,
    trimmed_string_or_null,
};

final class DsnParser
{
    /** @var array<string, string> */
    private const SCHEME_ALIASES = [
        'pdomysql'  => 'pdomysql',
        'mysql'     => 'pdomysql',
        'mysqli'    => 'mysqli',
    ];

    private function __construct() {}

    /**
     * Parse a database URL into an associative array.
     *
     * @param string $url
     * @return array{
     *     driver: string,
     *     host: string,
     *     port: int,
     *     db_name: string,
     *     charset: string,
     *     user: string,
     *     password: string,
     *     socket: string|null
     * }
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException('Database URL cannot be empty.', 400);
        }

        $dsn = parse_url($url);

        if ($dsn === false) {
            throw new InvalidArgumentException('Malformed database URL.', 400);
        }

        $scheme = strtolower(trim($dsn['scheme'] ?? 'pdomysql'));

        if (!isset(self::SCHEME_ALIASES[$scheme])) {
            throw new InvalidArgumentException(
                'Invalid "scheme" in database URL, must be one of: pdomysql, pdo_mysql, mysql, mysqli.',
                400
            );
        }

        $segments = [];

        if (isset($dsn['query'])) {
            parse_str($dsn['query'], $segments);
        }

        return [
            'driver'   => self::SCHEME_ALIASES[$scheme],
            'host'     => normalize_host($dsn['host'] ?? null, Connection::DEFAULT_HOST),
            'port'     => normalize_port($dsn['port'] ?? null, Connection::DEFAULT_PORT),
            'db_name'  => normalize_path($dsn['path'] ?? null),
            'charset'  => trimmed_string_or_default($segments['charset'] ?? null, Connection::DEFAULT_CHARSET),
            'user'     => decode_component($dsn['user'] ?? ''),
            'password' => decode_component($dsn['pass'] ?? ''),
            'socket'   => trimmed_string_or_null($segments['socket'] ?? null),
        ];
    }
}
