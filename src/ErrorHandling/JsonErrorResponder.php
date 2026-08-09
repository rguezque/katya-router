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
use rguezque\Contract\ErrorResponderInterface;
use rguezque\JsonResponse;
use rguezque\SapiEmitter;
use Throwable;

/**
 * JSON error responder for HTTP and CLI.
 */
final class JsonErrorResponder implements ErrorResponderInterface
{
    /**
     * {@inheritdoc}
     */
    public function respond(Throwable $exception, ErrorHandlerConfig $config): void
    {
        $payload = $this->payload($exception, $config);

        if (PHP_SAPI === 'cli') {
            fwrite(
                STDERR,
                json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ) . PHP_EOL
            );

            return;
        }

        $statusCode = $this->statusCode($exception);

        if (headers_sent()) {
            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return;
        }

        try {
            $response = new JsonResponse($payload, $statusCode);
            (new SapiEmitter())->emit($response);
        } catch (Throwable) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Build response payload.
     *
     * @param Throwable $exception
     * @param ErrorHandlerConfig $config
     * @return array
     */
    private function payload(Throwable $exception, ErrorHandlerConfig $config): array
    {
        $payload = [
            'error' => $config->debug
                ? $exception->getMessage()
                : $config->publicMessage,
        ];

        if (!$config->debug) {
            return $payload;
        }

        $payload['type'] = get_class($exception);
        $payload['code'] = $exception->getCode();
        $payload['file'] = $exception->getFile();
        $payload['line'] = $exception->getLine();
        $payload['trace'] = $exception->getTrace();
        $payload['trace_string'] = $exception->getTraceAsString();

        if ($exception instanceof ErrorException) {
            $payload['severity'] = $exception->getSeverity();
        }

        return $payload;
    }

    /**
     * Determine HTTP status code.
     *
     * @param Throwable $exception
     * @return int
     */
    private function statusCode(Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = (int) $exception->getStatusCode();

            if ($statusCode >= 400 && $statusCode <= 599) {
                return $statusCode;
            }
        }

        // Equivalent to HttpStatus::HTTP_INTERNAL_SERVER_ERROR
        return 500;
    }
}
