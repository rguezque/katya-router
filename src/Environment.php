<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use ErrorException;
use InvalidArgumentException;
use Throwable;
use function rguezque\functions\env;

/**
 * Represent the environment mode and error handling.
 *
 * This class provides methods to set and get the environment mode,
 * configure error handling, and log errors.
 * 
 * @method void register(?string $env_mode = null) Register error and exception handlers
 * @method string getMode() Get the current environment mode
 * @method void setLogPath(string $path) Set the log path for error logging
 * @method string getLogPath() Get the log path, empty string if not set
 * @method void logError(Throwable $exception) Log an error to the log file
 * 
 */
class Environment {
    const DEVELOPMENT = 'development';
    const PRODUCTION = 'production';
    const VALID_ENVIRONMENTS = [self::DEVELOPMENT, self::PRODUCTION];

    /**
     * Environment mode ("development" or "production")
     */
    private static string $mode = '';

    /**
     * Log path for error logging
     */
    private static string $log_path = '';

    /**
     * Display errors flag
     */
    private static bool $display_errors = true;

    /**
     * Register error and exception handlers
     *
     * @param string|null $env_mode Environment mode, development or production
     * @return void
     * @throws InvalidArgumentException
     */
    public static function register(?string $env_mode = null): void {
        self::initializeMode($env_mode);

        // Set error handler (respects @ suppression operator)
        set_error_handler(function(int $severity, string $message, string $file, int $line): bool {
            // Respect error suppression operator (@)
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        // Set exception handler
        set_exception_handler([self::class, 'handleException']);

        // Register shutdown function to catch fatal errors
        register_shutdown_function(function(): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $exception = new ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                );
                self::handleException($exception);
            }
        });
    }

    /**
     * Get the current environment mode
     *
     * @return string
     */
    public static function getMode(): string {
        if (empty(self::$mode)) {
            self::initializeMode();
        }
        return self::$mode;
    }

    /**
     * Set the log path for error logging
     *
     * @param string $path The directory for log file
     * @return void
     * @throws InvalidArgumentException
     */
    public static function setLogPath(string $path): void {
        if (empty($path)) {
            throw new InvalidArgumentException('Log path cannot be empty');
        }

        // Create directory if it doesn't exist
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                throw new InvalidArgumentException("Failed to create log directory: {$path}");
            }
        }

        // Check if directory is writable
        if (!is_writable($path)) {
            throw new InvalidArgumentException("Log directory is not writable: {$path}");
        }

        self::$log_path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php_errors.log';
    }

    /**
     * Get the log path, empty string if not set
     *
     * @return string
     */
    public static function getLogPath(): string {
        return self::$log_path;
    }

    /**
     * Log an error to the log file
     *
     * @param Throwable $exception
     * @return void
     */
    public static function logError(Throwable $exception): void {
        $log_path = self::getLogPath();
        $log_dir = dirname($log_path);

        // Ensure log directory exists
        if (!is_dir($log_dir)) {
            if (!mkdir($log_dir, 0755, true) && !is_dir($log_dir)) {
                // Silently fail if we can't create log directory
                return;
            }
        }

        $timestamp = time();
        $date = date('Y-m-d H:i:s', $timestamp);
        $log_message = sprintf(
            "[%s] %s.%d: %s in %s on line %d\n%s\n\n",
            $date,
            self::getSeverityName((int)$exception->getCode()),
            $exception->getCode(),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        // Use file locking to prevent race conditions
        file_put_contents($log_path, $log_message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Handle uncaught exceptions
     *
     * @param Throwable $exception
     * @return void
     */
    public static function handleException(Throwable $exception): void {
        // Log the error
        self::logError($exception);

        $is_dev = self::getMode() === self::DEVELOPMENT;
        $status_code = \rguezque\HttpStatus::HTTP_INTERNAL_SERVER_ERROR;

        // Prepare error response
        $error_data = [
            'error' => $is_dev ? $exception->getMessage() : 'Internal Server Error'
        ];

        // In development, include detailed information
        if ($is_dev) {
            $error_data['code'] = $exception->getCode();
            $error_data['file'] = $exception->getFile();
            $error_data['line'] = $exception->getLine();
            $error_data['trace'] = $exception->getTrace();
            $error_data['trace_string'] = $exception->getTraceAsString();
        }

        // Send error response
        try {
            $response = new \rguezque\JsonResponse($error_data, $status_code);
            \rguezque\SapiEmitter::emit($response);
        } catch (Throwable $e) {
            // Fallback if response sending fails
            http_response_code($status_code);
            header('Content-Type: application/json');
            echo json_encode($error_data);
        }

        exit(1);
    }

    /**
     * Initialize environment mode from .env
     *
     * @param string|null $env_mode Environment mode, development or production
     * @return void
     * @throws InvalidArgumentException
     */
    protected static function initializeMode(?string $env_mode = null): void {
        // Load mode from environment, default to 'development'
        $mode = strtolower($env_mode ?? env('APP_ENV', self::DEVELOPMENT));
        $mode = trim($mode);

        // Validate mode
        if (!in_array($mode, self::VALID_ENVIRONMENTS, true)) {
            throw new InvalidArgumentException(
                sprintf("Environment mode must be '%s' or '%s', '%s' given", 
                    self::DEVELOPMENT, 
                    self::PRODUCTION, 
                    $mode
                )
            );
        }

        self::$mode = $mode;
        self::configureErrorHandling();
    }

    /**
     * Configure error handling based on environment mode
     *
     * @return void
     */
    protected static function configureErrorHandling(): void {
        if (self::$mode === self::DEVELOPMENT) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            self::$display_errors = true;
        } else {
            // In production, log errors but don't display them
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            ini_set('log_errors', '1');
            self::$display_errors = false;
        }
    }

    /**
     * Get human-readable severity name
     *
     * @param int $severity
     * @return string
     */
    protected static function getSeverityName(int $severity): string {
        $severities = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $severities[$severity] ?? 'E_UNKNOWN';
    }
}