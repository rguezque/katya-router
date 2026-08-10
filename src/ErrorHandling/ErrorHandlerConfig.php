<?php

declare(strict_types=1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezique
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\ErrorHandling;

use InvalidArgumentException;

use function rguezque\functions\env;

/**
 * Immutable configuration for error handling.
 */
final class ErrorHandlerConfig
{
    private function __construct(
        public readonly EnvironmentMode $mode,
        public readonly bool $debug,
        public readonly bool $display_errors,
        public readonly bool $log_errors,
        public readonly ?string $log_file,
        public readonly string $public_message,
        public readonly int $error_reporting,
    ) {}

    /**
     * Create configuration from an array or environment variables.
     *
     * Expected options:
     *
     * - mode: ?string
     * - debug: ?bool|string
     * - display_errors: ?bool|string
     * - log_errors: ?bool|string
     * - log_path: ?string
     * - public_message: ?string
     * - error_reporting: ?int
     *
     * @param array $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        $mode = EnvironmentMode::fromString(
            (string) ($config['mode'] ?? env('APP_ENV', EnvironmentMode::Production->value))
        );

        $isDev = $mode->isDevelopment();

        $debug = self::toBool(
            $config['debug'] ?? env('APP_DEBUG', $isDev ? '1' : '0')
        );

        $displayErrors = self::toBool(
            $config['display_errors'] ?? ($debug ? '1' : '0')
        );

        $logErrors = self::toBool(
            $config['log_errors'] ?? true
        );

        $errorReporting = isset($config['error_reporting'])
            ? (int) $config['error_reporting']
            : ($isDev ? E_ALL : E_ALL & ~E_DEPRECATED);

        return new self(
            mode: $mode,
            debug: $debug,
            display_errors: $displayErrors,
            log_errors: $logErrors,
            log_file: self::prepareLogFile($config['log_path'] ?? null),
            public_message: (string) ($config['public_message'] ?? 'Internal Server Error'),
            error_reporting: $errorReporting,
        );
    }

    /**
     * Convert mixed values to boolean using filter_var.
     *
     * @param mixed $value
     * @return bool
     */
    private static function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    /**
     * Prepare log file path.
     *
     * @param mixed $path
     * @return string|null
     * @throws InvalidArgumentException
     */
    private static function prepareLogFile(mixed $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new InvalidArgumentException("Failed to create log directory: {$path}");
        }

        clearstatcache(false, $path);

        if (!is_writable($path)) {
            throw new InvalidArgumentException("Log directory is not writable: {$path}");
        }

        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php_errors.log';
    }
}
