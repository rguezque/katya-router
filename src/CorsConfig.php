<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use rguezque\HttpHeaders;
use rguezque\Request;

/**
 * Represent CORS configuration for handling cross-origin requests
 * 
 * This class provides methods to configure CORS for different origins,
 * allowing you to specify allowed HTTP methods, headers, and other CORS
 * settings. It can handle preflight requests and set appropriate CORS headers
 * for incoming requests based on the origin and request method.
 * 
 * @method CorsConfig addOrigin(string $origin, array $methods = ['*'], array $config = []) Add an origin with specific configuration
 * @method CorsConfig setDefaultConfig(array $config) Set global default configuration for CORS
 */
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
        'allowed_headers' => ['Content-Type'],
        'expose_headers'  => [],
        'max_age' => 86400, // 24 hours
        'supports_credentials' => false
    ];

    /**
     * Set an optional shared configuration for all origins. If no configuration is defined 
     * or a key is missing, a default configuration will be assigned.
     * 
     * @param array $shared_config Optional shared configuration
     */
    public function __construct(array $shared_config = []) {
        $this->default_config = array_merge($this->default_config, $shared_config);
    }

    /**
     * Add an origin with specific configuration
     * 
     * @param string $origin Origin URL
     * @param array $methods Allowed HTTP methods for this origin
     * @param array $config Additional CORS configuration for this origin
     * @return CorsConfig
     * @throws InvalidArgumentException When `origin` is the wildcard `*` and `supports_credentials` is set to `true`
     */
    public function addOrigin(string $origin, array $methods = ['*'], array $config = []): CorsConfig {
        $origin = trim($origin);
        $normalized_config = [
            'methods' => array_map(fn($m) => strtoupper(trim($m)), $methods),
            'config' => array_merge($this->default_config, $config)
        ];

        if('*' === $origin && $normalized_config['config']['supports_credentials']) {
            throw new InvalidArgumentException('For security reasons, the use of the wildcard "*" in the origin definition is prohibited when "supports_credentials" is set to true');
        }

        $this->origins[$origin] = $normalized_config;
        return $this;
    }

    /**
     * Set global default configuration
     * 
     * @param array $config Default CORS configuration
     * @return CorsConfig
     */
    public function setDefaultConfig(array $config): CorsConfig {
        $this->default_config = array_merge($this->default_config, $config);
        return $this;
    }

    /**
     * Handle CORS headers for a request
     * 
     * @param Request $request Incoming request
     * @return HttpHeaders|false CORS headers to apply, or false if no CORS handling is needed
     */
    public function handle(Request $request): HttpHeaders|false {
        $server = $request->getServer();
        $origin = $server->get('HTTP_ORIGIN');
        $request_method = $server->get('REQUEST_METHOD');

        // No origin, skip CORS handling
        if (!$origin) {
            return false;
        }

        // Find matching origin configuration
        $origin_config = $this->findOriginConfig($origin);
        
        // No matching origin found, allow request to continue
        if (!$origin_config) {
            return false;
        }

        // Check if the request method is allowed
        if (!$this->isMethodAllowed($origin_config, $request_method)) {
            return false;
        }

        // Apply CORS headers for the matching origin
        $headers = new HttpHeaders();
        // Credentials support
        $headers->set('Access-Control-Allow-Origin', $origin);
        $headers->set('Vary', 'Origin');
        
        if ($origin_config['config']['supports_credentials']) {
            $headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // Expose headers to frontend
        if (!empty($origin_config['config']['expose_headers'])) {
            $headers->set('Access-Control-Expose-Headers', implode(', ', $origin_config['config']['expose_headers']));
        }

        return $headers;
    }

    /**
     * Handle CORS preflight request
     * 
     * @param Request $request Incoming request
     * @return HttpHeaders|false CORS headers for preflight, or false if no CORS handling is needed
     */
    public function handlePreflight(Request $request): HttpHeaders|false {
        $server = $request->getServer();
        $origin = $server->get('HTTP_ORIGIN');
        $request_method = $server->get('HTTP_ACCESS_CONTROL_REQUEST_METHOD');
        if (!$request_method) return false;

        // No origin, skip CORS handling
        if (!$origin) {
            return false;
        }

        // Find matching origin configuration
        $origin_config = $this->findOriginConfig($origin);
        
        // No matching origin found, allow request to continue
        if (!$origin_config) {
            return false;
        }

        // Check if the request method is allowed
        if (!$this->isMethodAllowed($origin_config, $request_method)) {
            return false;
        }

        // OPCIONAL PERO RECOMENDADO: Validar cabeceras solicitadas
        $requested_headers = $server->get('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
        if ($requested_headers && !$this->areHeadersAllowed($origin_config, $requested_headers)) {
            return false;
        }
        
        $headers = new HttpHeaders();
        $headers->set('Access-Control-Allow-Origin', $origin);
        $headers->set(
            'Access-Control-Allow-Methods', 
            implode(', ', $this->getAllowedMethods($origin_config))
        );

        // Add allowed headers
        $headers->set(
            'Access-Control-Allow-Headers', 
            implode(', ', $origin_config['config']['allowed_headers'])
        );
        
        // Credentials support
        if ($origin_config['config']['supports_credentials']) {
            $headers->set('Access-Control-Allow-Credentials', 'true');
        }
        // Add max age for preflight caching
        if ($origin_config['config']['max_age']) {
            $headers->set('Access-Control-Max-Age', (string)$origin_config['config']['max_age']);
        }
        $headers->set('Vary', 'Origin');

        return $headers;
    }

    /**
     * Find configuration for a specific origin
     * 
     * @param string $origin Origin URL
     * @return array|null Origin configuration or null if not found
     */
    private function findOriginConfig(string $origin): ?array {
        // Check if the global wildcard configuration '*' exists
        if (isset($this->origins['*'])) {
            return $this->origins['*'];
        }

        // Check regex patterns
        foreach ($this->origins as $origin_pattern => $config) {
            // Skip the '*' if it appears here to avoid regex errors
            if ($origin_pattern === '*') { 
                continue; 
            }
            // Exact coincidence
            if ($origin_pattern === $origin) {
                return $config;
            }
            
            // Exact match delimiters are added if they have not been defined in the regex pattern
            $regex = $origin_pattern;
            if (!str_starts_with($regex, '^')) $regex = '^' . $regex;
            if (!str_ends_with($regex, '$')) $regex = $regex . '$';

            if (@preg_match('#' . $regex . '#', $origin)) {
                return $config;
            }
        }

        return null;
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
        return in_array('*', $origin_config['methods'], true)
            ? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']
            : $origin_config['methods'];
    }

    private function areHeadersAllowed(array $origin_config, string $requested_headers): bool {
        $allowed = array_map('strtolower', $origin_config['config']['allowed_headers']);
        $requested = array_map('strtolower', array_map('trim', explode(',', $requested_headers)));
        
        return empty(array_diff($requested, $allowed));
    }
}