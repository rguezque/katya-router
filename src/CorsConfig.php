<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

class CorsConfig {
    /**
     * Configuration for different origins and their allowed methods
     * @var array
     */
    private array $origins = [];

    /**
     * Global default configuration
     * @var array
     */
    private array $default_config = [
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'max_age' => 86400, // 24 hours
        'supports_credentials' => false
    ];

    /**
     * Add an origin with specific configuration
     * 
     * @param string $origin Origin URL
     * @param array $methods Allowed HTTP methods for this origin
     * @param array $config Additional CORS configuration for this origin
     * @return Cors
     */
    public function addOrigin(string $origin, array $methods = ['*'], array $config = []): CorsConfig {
        $this->origins[$origin] = [
            'methods' => $methods,
            'config' => array_merge($this->default_config, $config)
        ];
        return $this;
    }

    /**
     * Set global default configuration
     * 
     * @param array $config Default CORS configuration
     * @return Cors
     */
    public function setDefaultConfig(array $config): CorsConfig {
        $this->default_config = array_merge($this->default_config, $config);
        return $this;
    }

    /**
     * Handle CORS headers for a request
     * 
     * @param Request $request Incoming request
     * @param Response $response Response object to modify
     * @return bool Whether to continue processing the request
     */
    public function __invoke(Request $request, Response $response): bool {
        $server = $request->getServer();
        $origin = $server->get('HTTP_ORIGIN');
        $request_method = $server->get('REQUEST_METHOD');

        // No origin, skip CORS handling
        if (!$origin) {
            return true;
        }

        // Find matching origin configuration
        $origin_config = $this->findOriginConfig($origin);
        
        // No matching origin found, allow request to continue
        if (!$origin_config) {
            return true;
        }

        // Apply CORS headers for the matching origin
        $response->headers->set('Access-Control-Allow-Origin', $origin);

        // Credentials support
        if ($origin_config['config']['supports_credentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // Handle preflight requests (OPTIONS method)
        if ($request_method === 'OPTIONS') {
            return $this->handlePreflightRequest($request, $response, $origin_config);
        }
        
        SapiEmitter::emit($response);

        // Validate request method for non-preflight requests
        return $this->validateRequestMethod($origin_config, $request_method);
    }

    /**
     * Find configuration for a specific origin
     * 
     * @param string $origin Origin URL
     * @return array|null Origin configuration or null if not found
     */
    private function findOriginConfig(string $origin): ?array {
        foreach ($this->origins as $origin_pattern => $config) {
            if (preg_match('#' . $origin_pattern . '#', $origin)) {
                return $config;
            }
        }
        // Exact match
        /* if (isset($this->origins[$origin])) {
            return $this->origins[$origin];
        } */

        // Wildcard match
        foreach ($this->origins as $config_origin => $config) {
            if ($config_origin === '*') {
                return $config;
            }
        }

        return null;
    }

    /**
     * Handle preflight request
     * 
     * @param Request $request Incoming request
     * @param Response $response Response object
     * @param array $origin_config Origin configuration
     * @return bool Whether to continue processing
     */
    private function handlePreflightRequest(Request $request, Response $response, array $origin_config): bool {
        $requested_method = $request->getAllHeaders()->get('Access-Control-Request-Method');
        
        // Validate requested method
        if (!$this->isMethodAllowed($origin_config, $requested_method)) {
            return false;
        }

        // Add allowed methods
        $response->headers->set(
            'Access-Control-Allow-Methods', 
            implode(', ', $this->getAllowedMethods($origin_config))
        );

        // Add allowed headers
        $response->headers->set(
            'Access-Control-Allow-Headers', 
            implode(', ', $origin_config['config']['allowed_headers'])
        );

        // Add max age for preflight caching
        $response->headers->set(
            'Access-Control-Max-Age', 
            (string)$origin_config['config']['max_age']
        );

        // Respond immediately for preflight
        $response->setStatusCode(HttpStatus::HTTP_NO_CONTENT);
        SapiEmitter::emit($response);
        
        return true;
    }

    /**
     * Check if a method is allowed for a specific origin
     * 
     * @param array $origin_config Origin configuration
     * @param string $method HTTP method to check
     * @return bool Whether the method is allowed
     */
    private function isMethodAllowed(array $origin_config, string $method): bool {
        return in_array('*', $origin_config['methods'], true) || 
               in_array(strtoupper($method), $origin_config['methods'], true);
    }

    /**
     * Get all allowed methods for an origin
     * 
     * @param array $origin_config Origin configuration
     * @return array Allowed HTTP methods
     */
    private function getAllowedMethods(array $origin_config): array {
        return $origin_config['methods'][0] === '*' 
            ? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] 
            : $origin_config['methods'];
    }

    /**
     * Validate request method for a non-preflight request
     * 
     * @param array $origin_config Origin configuration
     * @param string $request_method HTTP method of the request
     * @return bool Whether the request method is allowed
     */
    private function validateRequestMethod(array $origin_config, string $request_method): bool {
        return $this->isMethodAllowed($origin_config, $request_method);
    }
}
