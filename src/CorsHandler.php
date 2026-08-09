<?php declare(strict_types = 1);

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
class CorsHandler implements MiddlewareInterface {
    private ?CorsConfig $cors_config = null;
    private HttpHeaders|false $cors_headers = false;
    private Response $response;

    /**
     * Set CORS configuration
     */
    public function __construct(CorsConfig $cors_config, Response $response) {
        $this->cors_config = $cors_config;
        $this->response = $response;
    }

    public function __invoke(Request $request, callable $next): Response {
        // Manejar preflight requests
        if($this->isPreflight($request)) {
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
        
        $this->response->setStatusCode(HttpStatus::HTTP_NO_CONTENT);
        $response = $this->applyCorsHeaders($this->response);
        
        return $response;
    }

    /**
     * Resolve CORS headers from config
     * 
     * @param Request $request Current request
     */
    public function resolveHeaders(Request $request): void {
        $this->cors_headers = false;

        if (null === $this->cors_config) {
            return;
        }

        $this->cors_headers = $this->cors_config->handle($request);
    }

    /**
     * Resolve CORS headers for preflight request
     */
    public function resolvePreflightHeaders(Request $request): void {
        $this->cors_headers = false;

        if (null === $this->cors_config) {
            return;
        }

        $this->cors_headers = $this->cors_config->handlePreflight($request);
    }

    /**
     * Apply CORS headers to response
     * 
     * @param Response $response Response object to add CORS headers
     * @return Response
     */
    public function applyCorsHeaders(Response $response): Response {
        if (!$this->cors_headers) {
            return $response;
        }

        foreach ($this->cors_headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
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