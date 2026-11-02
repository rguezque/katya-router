<?php

declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use ErrorException;
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
 * Origin matching rules:
 *  - A valid URL (with scheme) is matched literally against scheme://host[:port].
 *  - Any other string is treated as a PCRE regex (you must include delimiters or
 *    the class will wrap it with `~`).
 *  - The wildcard `*` matches any origin.
 *
 * @method CorsConfig addOrigin(string $origin, array $methods = ["*"], array $config = []) Add an origin with specific configuration
 */
class CorsConfig
{
    private const WILDCARD_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
    /**
     * Maximum number of entries kept in the origin lookup cache.
     * Prevents unbounded memory growth when many distinct origins are seen
     * (e.g. under a persistent runtime like RoadRunner/Swoole).
     */

    private const CACHE_LIMIT = 1000;
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
     * Cache for origin configuration lookups
     * @var array
     */
    private array $origin_cache = [];

    /**
     * Set an optional shared configuration for all origins. If no configuration is defined
     * or a key is missing, a default configuration will be assigned.
     *
     * @param array $shared_config Optional shared configuration
     */
    public function __construct(array $shared_config = [])
    {
        $this->default_config = array_merge($this->default_config, $shared_config);
    }

    /**
     * Add an origin with specific configuration
     *
     * @param string $origin Origin URL, regex pattern or the wildcard `*`
     * @param array $methods Allowed HTTP methods for this origin
     * @param array $config Additional CORS configuration for this origin
     * @return CorsConfig
     * @throws InvalidArgumentException When `origin` is the wildcard `*` and `supports_credentials` is set to true,
     *                                  or when the origin pattern is invalid.
     */
    public function addOrigin(string $origin, array $methods = ['*'], array $config = []): CorsConfig
    {
        $origin = trim($origin);
        $regex = null;
        if ('*' !== $origin) {
            // If it's a valid URL, normalize it (strip path/query/fragment) so that
            // `https://example.com/api` becomes `https://example.com`, which is what
            // browsers actually send in the Origin header.
            if (false !== filter_var($origin, FILTER_VALIDATE_URL)) {
                $origin = $this->normalizeUrlOrigin($origin);
            } else {
                // Treat as a PCRE regex pattern
                $regex = $this->buildOriginRegex($origin);
            }
        }

        $normalized_config = [
            'origin' => $origin,
            'methods' => array_map(fn($m) => strtoupper(trim($m)), $methods),
            'config' => array_merge($this->default_config, $config),
            'regex' => $regex
        ];

        if ('*' === $origin && $normalized_config['config']['supports_credentials']) {
            throw new InvalidArgumentException('For security reasons, the use of the wildcard "*" in the origin definition is prohibited when "supports_credentials" is set to true');
        }

        $this->origins[$origin] = $normalized_config;
        // Invalidate cache when adding new origin
        $this->origin_cache = [];
        
        return $this;
    }

    /**
     * Handle CORS headers for a request
     *
     * @param Request $request Incoming request
     * @return HttpHeaders|false CORS headers to apply, or false if no CORS handling is needed
     */
    public function handle(Request $request): HttpHeaders|false
    {
        $server = $request->getServer();
        $origin = $server->get('HTTP_ORIGIN');
        $request_method = $server->get('REQUEST_METHOD');

        // No origin, skip CORS handling
        if (!is_string($origin) || '' === $origin || !is_string($request_method) || '' === $request_method) {
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
        $allow_origin = '*' === $origin_config['origin'] ? '*' : $origin;
        $headers->set('Access-Control-Allow-Origin', $allow_origin);

        if ('*' !== $allow_origin) {
            $headers->set('Vary', 'Origin');
        }

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
    public function handlePreflight(Request $request): HttpHeaders|false
    {
        $server = $request->getServer();
        $origin = $server->get('HTTP_ORIGIN');
        $request_method = $server->get('HTTP_ACCESS_CONTROL_REQUEST_METHOD');

        // No origin, skip CORS handling
        if (!is_string($origin) || '' === $origin || !is_string($request_method) || '' === $request_method) {
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

        $requested_headers = $server->get('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
        if (null !== $requested_headers && (!is_string($requested_headers) || !$this->areHeadersAllowed($origin_config, $requested_headers))) {
            return false;
        }

        $headers = new HttpHeaders();
        $allow_origin = '*' === $origin_config['origin'] ? '*' : $origin;
        $headers->set('Access-Control-Allow-Origin', $allow_origin);
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

        $vary = 'Access-Control-Request-Method, Access-Control-Request-Headers';
        if ('*' !== $allow_origin) {
            $vary = 'Origin, ' . $vary;
        }
        $headers->set('Vary', $vary);
        
        return $headers;
    }

    /**
     * Find configuration for a specific origin
     *
     * @param string $origin Origin URL
     * @return array|null Origin configuration or null if not found
     */
    private function findOriginConfig(string $origin): ?array
    {
        // Check cache first
        if (isset($this->origin_cache[$origin])) {
            return $this->origin_cache[$origin];
        }

        if (isset($this->origins[$origin])) {
            $config = $this->origins[$origin];
            $this->cacheOrigin($origin, $config);
            return $config;
        }

        foreach ($this->origins as $config) {
            if (null === $config['regex']) {
                continue;
            }
            if (1 === preg_match($config['regex'], $origin)) {
                $this->cacheOrigin($origin, $config);
                return $config;
            }
        }

        $config = $this->origins['*'] ?? null;
        if ($config) {
            $this->cacheOrigin($origin, $config);
        }

        return $config;
    }

    /**
     * Store an entry in the origin cache, evicting the oldest entry when the
     * cache reaches its size limit (FIFO eviction).
     */
    private function cacheOrigin(string $origin, array $config): void
    {
        if (count($this->origin_cache) >= self::CACHE_LIMIT) {
            // array_key_first returns the oldest inserted key
            $oldest = array_key_first($this->origin_cache);
            if (null !== $oldest) {
                unset($this->origin_cache[$oldest]);
            }
        }
        $this->origin_cache[$origin] = $config;
    }

    /**
     * Compile and validate an origin pattern.
     *
     * The pattern is treated as a PCRE regular expression. If it does not start
     * with `^` or end with `$`, these anchors are added automatically. The
     * resulting regex is wrapped with `~` delimiters.
     */
    private function buildOriginRegex(string $origin_pattern): string
    {
        if ('' === $origin_pattern) {
            throw new InvalidArgumentException('CORS origin pattern cannot be empty.');
        }

        $regex = $origin_pattern;
        if (!str_starts_with($regex, '^')) {
            $regex = '^' . $regex;
        }

        if (!str_ends_with($regex, '$')) {
            $regex .= '$';
        }

        $regex = '~' . str_replace('~', '\~', $regex) . '~';
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        }, E_WARNING);

        try {
            $is_valid = false !== preg_match($regex, '');
        } catch (ErrorException $exception) {
            throw new InvalidArgumentException(
                sprintf('Invalid CORS origin pattern "%s".', $origin_pattern),
                0,
                $exception
            );
        } finally {
            restore_error_handler();
        }

        if (!$is_valid) {
            throw new InvalidArgumentException(sprintf('Invalid CORS origin pattern "%s".', $origin_pattern));
        }

        return $regex;
    }

    /**
     * Normalize a URL origin by keeping only scheme, host and (optional) port.
     * Browsers never send path/query/fragment in the Origin header, so any
     * extra component would make the entry impossible to match.
     */
    private function normalizeUrlOrigin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    /**
     * Check if a method is allowed for a specific origin
     *
     * @param array $origin_config Origin configuration
     * @param string $method HTTP method to check
     * @return bool Whether the method is allowed
     */
    private function isMethodAllowed(array $origin_config, string $method): bool
    {
        $method = strtoupper(trim($method));
        return (
            in_array('*', $origin_config['methods'], true)
            && in_array($method, self::WILDCARD_METHODS, true)
        ) || in_array($method, $origin_config['methods'], true);
    }

    /**
     * Get all allowed methods for an origin
     *
     * @param array $origin_config Origin configuration
     * @return array Allowed HTTP methods
     */
    private function getAllowedMethods(array $origin_config): array
    {
        return in_array('*', $origin_config['methods'], true)
            ? self::WILDCARD_METHODS
            : $origin_config['methods'];
    }

    /**
     * Check whether every header requested by the client is present in the
     * origin's allowed_headers list.
     *
     * An empty `Access-Control-Request-Headers` value is considered valid
     * (browsers may send it empty when no custom headers are required).
     */
    private function areHeadersAllowed(array $origin_config, string $requested_headers): bool
    {
        $allowed = array_map('strtolower', $origin_config['config']['allowed_headers']);
        // If any header with '*' is allowed, return true immediately
        if (in_array('*', $allowed, true)) {
            return true;
        }
        $trimmed = trim($requested_headers);
        // Empty request-headers is valid: the client is not asking for any custom header.
        if ('' === $trimmed) {
            return true;
        }
        $requested = array_map('strtolower', array_map('trim', explode(',', $trimmed)));
        // Remove empty entries that could appear from trailing commas
        $requested = array_filter($requested, static fn(string $h): bool => '' !== $h);
        return empty(array_diff($requested, $allowed));
    }
}
