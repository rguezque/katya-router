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
use Throwable;

/**
 * Default file/error_log logger.
 */
final class FileErrorLogger implements ErrorLoggerInterface
{
    /**
     * {@inheritdoc}
     */
    public function log(Throwable $exception, ErrorHandlerConfig $config): void
    {
        if (!$config->logErrors) {
            return;
        }

        $message = $this->format($exception);

        if ($config->logFile !== null) {
            $directory = dirname($config->logFile);

            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }

            if (@file_put_contents($config->logFile, $message, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        }

        error_log($message);
    }

    /**
     * Format exception as log line.
     *
     * @param Throwable $exception
     * @return string
     */
    private function format(Throwable $exception): string
    {
        [$severityName, $severityCode] = $this->severity($exception);

        return sprintf(
            "[%s] %s.%d: %s: %s in %s on line %d\n%s\n",
            date('Y-m-d H:i:s'),
            $severityName,
            $severityCode,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }

    /**
     * Get severity name and code.
     *
     * @param Throwable $exception
     * @return array{0: string, 1: int}
     */
    private function severity(Throwable $exception): array
    {
        $severity = $exception instanceof ErrorException
            ? $exception->getSeverity()
            : E_ERROR;

        return [
            $this->severityName($severity),
            $severity,
        ];
    }

    /**
     * Get human-readable severity name.
     *
     * @param int $severity
     * @return string
     */
    private function severityName(int $severity): string
    {
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
            2048 => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return $severities[$severity] ?? 'E_UNKNOWN';
    }
}
