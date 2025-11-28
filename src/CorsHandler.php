<?php declare(strict_types = 1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Handles CORS preflight requests and adds CORS headers to responses
 */
class CorsHandler {
    private ?CorsConfig $cors_config = null;
    private HttpHeaders|false $cors_headers = false;

    public function __construct(?CorsConfig $cors_config = null) {
        $this->cors_config = $cors_config;
    }

    /**
     * Set CORS configuration
     */
    public function setConfig(CorsConfig $cors_config): self {
        $this->cors_config = $cors_config;
        return $this;
    }

    /**
     * Check if request is a CORS preflight request
     */
    public function isPreflight(Request $request): bool {
        $server = $request->getServer();
        return strtoupper($server->get('REQUEST_METHOD')) === 'OPTIONS' 
            && $server->get('HTTP_ORIGIN') !== null 
            && $server->get('HTTP_ACCESS_CONTROL_REQUEST_METHOD') !== null;
    }

    /**
     * Handle preflight request and return response
     */
    public function handlePreflight(Request $request): Response {
        $this->resolvePreflightHeaders($request);
        
        $response = new Response('', 204);
        $this->applyHeadersToResponse($response);
        
        return $response;
    }

    /**
     * Resolve CORS headers from config
     */
    public function resolveHeaders(Request $request): void {
        if (null === $this->cors_config) {
            return;
        }

        $this->cors_headers = $this->cors_config->handle($request);
    }

    /**
     * Resolve CORS headers for preflight request
     */
    public function resolvePreflightHeaders(Request $request): void {
        if (null === $this->cors_config) {
            return;
        }

        $this->cors_headers = $this->cors_config->handlePreflight($request);
    }

    /**
     * Apply CORS headers to response
     */
    public function applyHeadersToResponse(Response &$response): void {
        if (!$this->cors_headers) {
            return;
        }

        $this->cors_headers->rewind();
        while ($this->cors_headers->valid()) {
            $key = $this->cors_headers->key();
            $value = $this->cors_headers->current();
            $response->headers->set($key, $value);
            $this->cors_headers->next();
        }
    }

    /**
     * Get CORS headers collection
     */
    public function getHeaders(): ?HttpHeaders {
        return $this->cors_headers;
    }

    /**
     * Check if CORS is enabled
     */
    public function isEnabled(): bool {
        return null !== $this->cors_config;
    }
}