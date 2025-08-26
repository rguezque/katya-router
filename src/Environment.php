<?php declare(strict_types = 1);
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

/**
 * Represent the environment mode and error handling.
 * 
 * This class provides methods to set and get the environment mode,
 * configure error handling, and log errors.
 * 
 * @static void register(?string $env_mode = null) Register error and exception handlers
 * @static void setLogPath(string $path) Set the log path for error logging
 * @static string getLogPath() Get the log path, with a default if not set
 * @static string getMode() Get the current environment mode
 */
class Environment {
    /**
     * Environment mode ("development" or "production")
     * 
     * @var string
     */
    private static string $mode = '';

    /**
     * Log path for error logging
     * 
     * @var string
     */
    private static string $log_path = '';
    
    /**
     * Display errors
     * 
     * @var bool
     */
    private static bool $display_errors = true;

    /**
     * Initialize environment mode from .env
     * 
     * @param string $env_mode Environment mode, development or production
     * @return void
     * @throws InvalidArgumentException
     */
    private static function initializeMode(?string $env_mode = null): void {
        // Load mode from environment, default to 'development'
        $env_mode = strtolower($env_mode ?? $_ENV['APP_ENV'] ?? 'development');
        
        // Validate mode
        if (!in_array(trim($env_mode), ['development', 'production'])) {
            throw new InvalidArgumentException("Environment mode must be 'development' or 'production'");
        }
        
        // Set mode and configure error handling
        self::$mode = trim($env_mode);
        self::configureErrorHandling();
    }

    /**
     * Get the current environment mode
     * 
     * @return string
     */
    public static function getMode(): string {
        // Ensure mode is initialized from environment
        if (empty(self::$mode)) {
            self::initializeMode();
        }
        return self::$mode;
    }

    /**
     * Set the log path for error logging. The directory must already exists
     * 
     * @param string $path The directory for log file
     * @return void
     */
    public static function setLogPath(string $path): void {
        // Ensure the directory exists
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        // Append php_errors.log to the directory path
        self::$log_path = rtrim($path, '/') . '/php_errors.log';
    }

    /**
     * Get the log path, with a default if not set
     * 
     * @return string
     */
    public static function getLogPath(): string {
        return self::$log_path ?: dirname(__DIR__, 2) . '/logs/php_errors.log';
    }

    /**
     * Configure error handling based on environment mode
     * 
     * @return void
     */
    private static function configureErrorHandling(): void {
        if (self::$mode === 'development') {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            self::$display_errors = true;
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
            self::$display_errors = false;
        }
    }

    /**
     * Log an error to the log file
     * 
     * @param Throwable $exception
     * @return void
     */
    public static function logError(Throwable $exception): void {
        $log_message = sprintf(
            "[%s] %s in %s on line %d\n%s\n\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        // Ensure log directory exists
        $log_dir = dirname(self::getLogPath());
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        // Append to log file
        file_put_contents(self::getLogPath(), $log_message, FILE_APPEND);
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

        $error_data = [
            'error' => self::$mode == 'development' ? $exception->getMessage() : 'An internal server error occurred.',
            'code' => self::$mode == 'development' ? $exception->getCode() : HttpStatus::HTTP_INTERNAL_SERVER_ERROR
        ];

        // In development, include more details
        if (self::getMode() === 'development') {
            $error_data['file'] = $exception->getFile();
            $error_data['line'] = $exception->getLine();
            $error_data['trace'] = $exception->getTrace();
            $error_data['trace_string'] = $exception->getTraceAsString();
        }
        
        // Prepare response
        $response = new JsonResponse($error_data, HttpStatus::HTTP_INTERNAL_SERVER_ERROR);
        // Send error response
        SapiEmitter::emit($response);
        exit;
    }

    /**
     * Register error and exception handlers
     * 
     * @param ?string $env_mode Environment mode, development or production
     * @return void
     * @throws ErrorException
     */
    public static function register(?string $env_mode = null): void {
        // Initialize mode first
        self::initializeMode($env_mode);

        // Set default error handler
        set_error_handler(function($severity, $message, $file, $line) {
            if (error_reporting() & $severity) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
        });

        // Set exception handler
        set_exception_handler([self::class, 'handleException']);
    }
}