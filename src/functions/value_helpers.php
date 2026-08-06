<?php

declare(strict_types=1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\functions;

use InvalidArgumentException;

if (!function_exists('trimmed_string_or_null')) {
    /**
     * Returns a trimmed string or null if the value is empty or invalid.
     * 
     * @param mixed $value The value to normalize
     * @return string|null The normalized string or null
     */
    function trimmed_string_or_null(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

if (!function_exists('trimmed_string_or_default')) {
    /**
     * Returns a trimmed string or a default value if the value is empty or invalid.
     *
     * @param mixed $value The value to normalize
     * @param string $default The default value
     * @return string The normalized string or the default value
     */
    function trimmed_string_or_default(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }
}

if (!function_exists('string_or_default')) {
    /**
     * Returns a string without trimming.
     * Useful for credentials, because passwords may contain intentional spaces.
     * 
     * @param mixed $value The value to normalize
     * @param string $default The default value
     * @return string The normalized string or the default value
     */
    function string_or_default(mixed $value, string $default): string
    {
        if ($value === null || $value === false) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }
}


if (!function_exists('normalize_port')) {
    /**
     * Normalizes and validates a port value.
     *
     * Port 0 is treated as "use default port" when min is 0.
     * 
     * @param mixed $port The port value to normalize
     * @param int $default The default port to return if the value is empty or invalid (default: 3306)
     * @param int $min The minimum valid port number (default: 0)
     * @param int $max The maximum valid port number (default: 65535)
     * @return int The normalized port number
     * @throws InvalidArgumentException When the port is not a valid integer within the specified range
     */
    function normalize_port(
        mixed $port,
        int $default = 3306,
        int $min = 0,
        int $max = 65535
    ): int {
        if ($port === null || $port === '' || $port === false) {
            return $default;
        }

        if (!is_scalar($port)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid port "%s"; it must be an integer between %d and %d.',
                    gettype($port),
                    $min,
                    $max
                )
            );
        }

        $normalized = filter_var($port, FILTER_VALIDATE_INT);

        if ($normalized === false || $normalized < $min || $normalized > $max) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid port "%s"; it must be an integer between %d and %d.',
                    (string) $port,
                    $min,
                    $max
                )
            );
        }

        return $normalized === 0 ? $default : $normalized;
    }
}

if (!function_exists('normalize_path')) {
    /**
     * Normalize the path parameter.
     *
     * @param ?string $path The path value to normalize
     * @return string
     */
    function normalize_path(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return '';
        }

        return trim(rawurldecode($path));
    }
}

if (!function_exists('normalize_host')) {
    /**
     * Normalize and validate a `host` value. Usually used for database connections.
     *
     * @param mixed $host The host value to normalize
     * @param string $default The default host to return if the value is empty or invalid (default: '127.0.0.1')
     * @return string
     */
    function normalize_host(mixed $host, string $default = '127.0.0.1'): string
    {
        $host = trim(decode_component($host));

        // Allows IPv6 URLs like mysql://[::1]:3306/db
        if ($host !== '' && str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        return $host === '' ? $default : $host;
    }
}

if (!function_exists('decode_component')) {
    /**
     * Decode a URL component.
     *
     * @param mixed $value The value to decode
     * @return string
     */
    function decode_component(mixed $value): string
    {
        return is_string($value) ? rawurldecode($value) : '';
    }
}
