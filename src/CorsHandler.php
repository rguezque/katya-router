<?php

declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use rguezque\Contract\MiddlewareInterface;

/**
 * Handles CORS preflight requests and adds CORS headers to responses
 */
class CorsHandler implements MiddlewareInterface
{
    /** @var ?HttpHeaders */
    private ?HttpHeaders $cors_headers = null;

    /**
     * Set CORS configuration
     */
    public function __construct(private readonly CorsConfig $cors_config, private bool $enabled = true) {}

    public function __invoke(Request $request, callable $next): Response
    {
        // If CORS is disabled, just pass the request to the next middleware
        if (!$this->enabled) {
            return $next($request);
        }

        // Manejar preflight requests
        if ($this->isPreflight($request)) {
            return $this->handlePreflight($request);
        }
        // Resolve CORS headers
        $this->resolveHeaders($request);
        // Apply resolved CORS headers to response
        $response = $next($request);
        
        return $this->applyCorsHeaders($response);
    }

    /**
     * Check if request is a CORS preflight request
     */
    public function isPreflight(Request $request): bool
    {
        $server = $request->getServer();
        $origin = $server->get('HTTP_ORIGIN');
        $request_method = $server->get('HTTP_ACCESS_CONTROL_REQUEST_METHOD');
        return 'OPTIONS' === $request->getRequestMethod()
            && is_string($origin)
            && '' !== $origin
            && is_string($request_method)
            && '' !== trim($request_method);
    }

    /**
     * Handle preflight request and return response
     */
    public function handlePreflight(Request $request): Response
    {
        $this->resolvePreflightHeaders($request);
        $response = new Response();
        $response->setStatusCode(HttpStatus::HTTP_NO_CONTENT);
        return $this->applyCorsHeaders($response);
    }

    /**
     * Resolve CORS headers from config
     *
     * @param Request $request Current request
     */
    public function resolveHeaders(Request $request): void
    {
        $this->cors_headers = $this->cors_config->handle($request) ?: null;
    }

    /**
     * Resolve CORS headers for preflight request
     */
    public function resolvePreflightHeaders(Request $request): void
    {
        $this->cors_headers = $this->cors_config->handlePreflight($request) ?: null;
    }

    /**
     * Apply CORS headers to response
     *
     * When a `Vary` header already exists on the response, the CORS-related
     * values are merged into it. If either the existing value or the new one
     * is `*`, the result is collapsed to `*` (per RFC 7231 §7.1.4).
     *
     * @param Response $response Response object to add CORS headers
     * @return Response
     */
    public function applyCorsHeaders(Response $response): Response
    {
        if (null === $this->cors_headers) {
            return $response;
        }
        foreach ($this->cors_headers as $key => $value) {
            if ('vary' !== strtolower($key)) {
                $response->headers->set($key, $value);
                continue;
            }
            $existing_vary = $response->headers->get('Vary');
            if (null === $existing_vary) {
                $response->headers->set($key, $value);
                continue;
            }
            if ('*' === trim($existing_vary) || '*' === trim($value)) {
                $response->headers->set($key, '*');
                continue;
            }
            $vary_values = [];
            foreach (explode(',', $existing_vary . ', ' . $value) as $vary_value) {
                $vary_value = trim($vary_value);
                if ('' !== $vary_value) {
                    // Preserve original casing but deduplicate case-insensitively
                    $vary_values[strtolower($vary_value)] = $vary_value;
                }
            }
            $response->headers->set($key, implode(', ', $vary_values));
        }
        return $response;
    }

    /**
     * Get CORS headers collection
     */
    public function getHeaders(): ?HttpHeaders
    {
        return $this->cors_headers;
    }

    /**
     * Enable or disable CORS handling
     * 
     * @param bool $enabled Whether to enable or disable CORS handling
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Check if CORS is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
