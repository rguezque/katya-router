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
use rguezque\Exception\DuplicityException;

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
 * 
 * This class supports various database schemes and allows for key remapping through a provided keymap.
 * It validates the input URL and ensures that the output keys are unique, throwing exceptions for invalid or duplicate keys.
 * 
 * @method __construct(array<string, string> $keymap = []) Constructor that accepts an optional keymap for renaming output keys.
 * @method array<string, mixed> parse(string $url) Parses a database URL into an associative array of connection parameters.
 */
final class DsnParser
{
    /** @var array<string, string> Aliases for database schemes */
    private const SCHEME_ALIASES = [
        'pdomysql' => 'pdomysql',
        'mysql'    => 'pdomysql',
        'mysqli'   => 'mysqli',
    ];

    /** @var array<string, string> Map to rename output keys: original key => new key */
    private array $keymap;

    /** @var array<string, mixed> Parameters parsed from URL */
    private array $params;

    /**
     * @param array<string, string> $keymap original key => new key
     */
    public function __construct(array $keymap = [])
    {
        $this->keymap = $keymap;
    }

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

        $params = [
            'driver'   => self::SCHEME_ALIASES[$scheme],
            'host'     => normalize_host($dsn['host'] ?? null, Connection::DEFAULT_HOST),
            'port'     => normalize_port($dsn['port'] ?? null, Connection::DEFAULT_PORT),
            'db_name'  => normalize_path($dsn['path'] ?? null),
            'charset'  => trimmed_string_or_default($segments['charset'] ?? null, Connection::DEFAULT_CHARSET),
            'user'     => decode_component($dsn['user'] ?? ''),
            'password' => decode_component($dsn['pass'] ?? ''),
            'socket'   => trimmed_string_or_null($segments['socket'] ?? null),
        ];

        $this->params = $this->applyKeyMap($params);

        return $this->params;
    }

    /**
     * Applies the keymap defined in the constructor to rename output keys.
     *
     * The keymap uses the following convention:
     *
     * [
     *     'original_key' => 'new_key',
     * ]
     *
     * @param array<string, mixed> $params Parameters to apply the keymap to.
     * @return array<string, mixed> Parameters with keys renamed according to the keymap.
     *
     * @throws InvalidArgumentException
     * @throws DuplicityException
     */
    private function applyKeyMap(array $params): array
    {
        if ([] === $this->keymap) {
            return $params;
        }

        $mapped = [];

        foreach ($params as $key => $value) {
            $output_key = $key;

            if (array_key_exists($key, $this->keymap)) {
                $output_key = $this->keymap[$key];

                if (!is_string($output_key) || '' === trim($output_key)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Invalid keymap value for "%s". The mapped key must be a non-empty string.',
                            $key
                        ),
                        400
                    );
                }

                $output_key = trim($output_key);
            }

            if (array_key_exists($output_key, $mapped)) {
                throw new DuplicityException(
                    sprintf(
                        'Duplicate mapped key "%s". Review the keymap to avoid collisions.',
                        $output_key
                    ),
                    400
                );
            }

            $mapped[$output_key] = $value;
        }

        return $mapped;
    }
}
