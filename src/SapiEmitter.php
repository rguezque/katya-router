<?php

declare(strict_types=1);

namespace rguezque;

use InvalidArgumentException;
use RuntimeException;

/**
 * Sapi Emitter
 *
 * Send an HTTP response using PHP's SAPI layer.
 */
final class SapiEmitter
{
    /**
     * Read size for output by chunks.
     */
    private const CHUNK_SIZE = 8192;

    /**
     * Send the full HTTP response.
     *
     * @param Response $response The response to return to the client
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function emit(Response $response): void
    {
        if (headers_sent($file, $line)) {
            throw new RuntimeException("Headers already sent in $file on line $line.");
        }

        $status_code = $response->getStatusCode();
        $this->assertStatusCode($status_code);

        // Specific validation for redirects.
        if ($response instanceof RedirectResponse) {
            $location = $response->headers->get('Location');

            if (null === $location || '' === trim((string) $location)) {
                throw new RuntimeException('RedirectResponse requires a Location header.');
            }

            if ($status_code < 300 || $status_code >= 400) {
                throw new InvalidArgumentException('RedirectResponse should use a 3xx status code.');
            }
        }

        http_response_code($status_code);

        // Headers must also be emitted in redirects.
        $this->emitHeaders($response->headers);

        // Not all responses must issue a body.
        if ($this->shouldEmitBody($response, $status_code)) {
            $this->emitBody($response->body);
        }
    }

    /**
     * Determines whether the response should emit a body.
     *
     * @param Response $response Response to evaluate
     * @param int $status_code HTTP status to evaluate
     * @return bool
     */
    private function shouldEmitBody(Response $response, int $status_code): bool
    {
        // A body is typically not sent with redirects.
        if ($response instanceof RedirectResponse) {
            return false;
        }

        // Informative responses and bodiless codes.
        if ($status_code < 200 || 204 === $status_code || 205 === $status_code || 304 === $status_code) {
            return false;
        }

        // HEAD must not return a body.
        $method = Request::fromGlobals()->getRequestMethod() ?? 'GET';

        return 'HEAD' !== $method;
    }

    /**
     * Sends HTTP headers.
     *
     * @param HttpHeaders $headers Headers to send
     * @return void
     */
    private function emitHeaders(HttpHeaders $headers): void
    {
        foreach ($headers as $name => $value) {
            $name = $this->normalizeHeaderName((string) $name);

            // Allows single values ​​or multiple values.
            $values = is_iterable($value) ? $value : [$value];

            $isset_cookie = 0 === strcasecmp($name, 'Set-Cookie');
            $replace = !$isset_cookie;
            $first = true;

            foreach ($values as $header_value) {
                if (null === $header_value) {
                    continue;
                }

                $header_value = trim((string) $header_value);
                $this->assertValidHeaderValue($header_value);

                header($name . ': ' . $header_value, $first && $replace);
                $first = false;
            }
        }
    }

    /**
     * Send the response body.
     *
     * If Stream supports read()/eof(), it emits in chunks.
     * If not, use getContents() as a fallback.
     *
     * @param Stream $body Response body to send
     * @return void
     */
    private function emitBody(Stream $body): void
    {
        $body->rewind();

        // Optimal chunk-based output if the stream allows it.
        if (method_exists($body, 'eof') && method_exists($body, 'read')) {
            while (!$body->eof()) {
                $chunk = $body->read(self::CHUNK_SIZE);

                if ('' === $chunk) {
                    break;
                }

                echo $chunk;
            }

            return;
        }

        echo $body->getContents();
    }

    /**
     * Validates that the HTTP code is valid.
     *
     * @param int $status_code HTTP status code
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertStatusCode(int $status_code): void
    {
        if ($status_code < 100 || $status_code > 599) {
            throw new InvalidArgumentException("Invalid HTTP status code: $status_code");
        }
    }

    /**
     * Normalizes the header name.
     *
     * @param string $name Header name
     * @return string
     */
    private function normalizeHeaderName(string $name): string
    {
        $name = trim($name);
        $this->assertValidHeaderName($name);

        return ucwords(strtolower($name), '-');
    }

    /**
     * Avoid invalid header names.
     *
     * @param string $name Header name
     * @return void
     * @throws RuntimeException
     */
    private function assertValidHeaderName(string $name): void
    {
        if ('' === $name || preg_match('/[\x00-\x20\x7F:]/', $name)) {
            throw new RuntimeException('Invalid HTTP header name.');
        }
    }

    /**
     * Prevent CRLF injection in header values.
     *
     * @param string $value Header to evaluate
     * @return void
     * @throws RuntimeException
     */
    private function assertValidHeaderValue(string $value): void
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new RuntimeException('Invalid HTTP header value: CRLF injection detected.');
        }
    }
}
