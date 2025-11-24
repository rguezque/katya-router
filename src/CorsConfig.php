<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use RuntimeException;

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
 * @method bool __invoke(Request $request, Response $response) Handle CORS headers
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
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'max_age' => 86400, // 24 hours
        'supports_credentials' => false
    ];

    private $headers;

    public function __construct()
    {
        $this->headers = new HttpHeaders();
    }

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
     * @return HttpHeaders 
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function __invoke(Request $request): HttpHeaders {
        if(headers_sent($file, $line)) {
            new RuntimeException(sprintf('Headers sent from file "%s" line %s', $file, $line));
        }

        $server = $request->getServer();
        $origin = $server->get('HTTP_ORIGIN');
        $request_method = $server->get('REQUEST_METHOD');

        // No origin, skip CORS handling
        if (!$origin) {
            throw new RuntimeException('Something went wrong. Origin of a cross-origin request, not detected.');
        }

        // Find matching origin configuration
        $origin_config = $this->findOriginConfig($origin);
        
        // No matching origin found, allow request to continue
        if (!$origin_config) {
            throw new RuntimeException('Has been blocked by CORS policy. You do not have permissions configured for this resource.');
        }

        // Apply CORS headers for the matching origin
        $this->headers->set('Access-Control-Allow-Origin', $origin);

        $this->headers->set(
            'Access-Control-Allow-Methods', 
            implode(', ', $this->getAllowedMethods($origin_config))
        );

        // Add allowed headers
        $this->headers->set(
            'Access-Control-Allow-Headers', 
            implode(', ', $origin_config['config']['allowed_headers'])
        );

        // Credentials support
        if ($origin_config['config']['supports_credentials']) {
            $this->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // Add max age for preflight caching
        if ($origin_config['config']['max_age']) {
            $this->headers->set('Access-Control-Max-Age', (string)$origin_config['config']['max_age']);
        }

        // Validate request method for non-preflight requests
        if(!$this->isMethodAllowed($origin_config, $request_method)) {
            throw new InvalidArgumentException(sprintf('The request HTTP method "%s" is not allowed for origin: %s', $request_method, $origin));
        }

        return $this->headers;
    }

    /**
     * Find configuration for a specific origin
     * 
     * @param string $origin Origin URL
     * @return array|null Origin configuration or null if not found
     */
    private function findOriginConfig(string $origin): ?array {
        // PRIORIDAD: Chequear si existe la configuración global wildcard '*'
        // Esto evita que el '*' entre al preg_match y rompa el código.
        if (isset($this->origins['*'])) {
            return $this->origins['*'];
        }

        // Chequear patrones Regex
        foreach ($this->origins as $origin_pattern => $config) {
            // Saltamos el '*' si llegara a estar aquí para evitar error de regex
            if ($origin_pattern === '*') { 
                continue; 
            }
            
            // Usamos @ para suprimir warnings de regex mal formados por el usuario,
            // o idealmente deberías validar que sea un regex válido al hacer addOrigin
            if (@preg_match('#' . $origin_pattern . '#', $origin)) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Handle preflight request
     * 
     * @param Request $request Incoming request
     * @param array $origin_config Origin configuration
     * @return bool Whether to continue processing
     */
    private function handlePreflightRequest(Request $request, array $origin_config): bool {
        // Add allowed methods
        $this->headers->set(
            'Access-Control-Allow-Methods', 
            implode(', ', $this->getAllowedMethods($origin_config))
        );

        // Add allowed headers
        $this->headers->set(
            'Access-Control-Allow-Headers', 
            implode(', ', $origin_config['config']['allowed_headers'])
        );

        // Add max age for preflight caching
        $this->headers->set(
            'Access-Control-Max-Age', 
            (string)$origin_config['config']['max_age']
        );
        http_response_code(200);
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

}
