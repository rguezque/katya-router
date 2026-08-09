<?php

declare(strict_types=1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezique
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\ErrorHandling;

use ErrorException;
use rguezque\Contract\ErrorLoggerInterface;
use rguezque\Contract\ErrorResponderInterface;
use Throwable;

/**
 * Application error handler.
 *
 * Registers error, exception and shutdown handlers.
 */
final class ErrorHandler
{
    private readonly ErrorLoggerInterface $logger;
    private readonly ErrorResponderInterface $responder;

    public function __construct(
        private readonly ErrorHandlerConfig $config,
        ?ErrorLoggerInterface $logger = null,
        ?ErrorResponderInterface $responder = null,
    ) {
        $this->logger = $logger ?? new FileErrorLogger();
        $this->responder = $responder ?? new JsonErrorResponder();
    }

    /**
     * Register all error and exception handlers.
     *
     * @return void
     */
    public function register(): void
    {
        $this->applyIniSettings();

        set_error_handler($this->convertErrorToException(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    /**
     * Handle uncaught exceptions.
     *
     * @param Throwable $exception
     * @return never
     */
    public function handleException(Throwable $exception): never
    {
        try {
            $this->logger->log($exception, $this->config);
        } catch (Throwable $loggingError) {
            error_log(
                sprintf(
                    'Error logging failed: %s in %s on line %d',
                    $loggingError->getMessage(),
                    $loggingError->getFile(),
                    $loggingError->getLine()
                )
            );
        }

        try {
            $this->responder->respond($exception, $this->config);
        } catch (Throwable $responderError) {
            $this->fallbackRespond($responderError);
        }

        exit(1);
    }

    /**
     * Convert PHP errors into exceptions.
     *
     * @param int $severity
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     * @throws ErrorException
     */
    private function convertErrorToException(
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        // Respect error suppression operator (@)
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle fatal errors using shutdown handler.
     *
     * @return void
     */
    private function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        $this->handleException(
            new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            )
        );
    }

    /**
     * Apply PHP ini settings based on configuration.
     *
     * @return void
     */
    private function applyIniSettings(): void
    {
        error_reporting($this->config->errorReporting);

        ini_set('display_errors', $this->config->displayErrors ? '1' : '0');
        ini_set('display_startup_errors', $this->config->displayErrors ? '1' : '0');
        ini_set('log_errors', $this->config->logErrors ? '1' : '0');

        if ($this->config->logFile !== null) {
            ini_set('error_log', $this->config->logFile);
        }
    }

    /**
     * Fallback response if the main responder fails.
     *
     * @param Throwable $error
     * @return void
     */
    private function fallbackRespond(Throwable $error): void
    {
        $message = $this->config->debug
            ? $error->getMessage()
            : $this->config->publicMessage;

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $message . PHP_EOL);

            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo $message;
    }
}
