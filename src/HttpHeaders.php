<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Iterator;

/**
 * Represents a collection of HTTP headers.
 * Provides methods for setting, getting, removing, and iterating over headers.
 */
class HttpHeaders {
    /**
     * Internal storage for HTTP headers.
     * Keys are stored in lowercase to ensure case-insensitive access.
     * 
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * Internal pointer for iterator implementation.
     * Tracks the current position during iteration.
     * 
     * @var int
     */
    private int $position = 0;

    public function __construct(array $headers = []) {
        if([] !== $headers) {
            $keys = array_keys($headers);
            $normalized_keys = array_map(function($item) {
                return strtolower(trim($item));
            }, $keys);
            $values = array_values($headers);
            $this->headers = array_combine($normalized_keys, $values);
        }
    }

    /**
     * Sets a header value, converting the key to lowercase.
     * 
     * @param string $key The header name
     * @param string $value The header value
     * @return HttpHeaders The current HttpHeaders instance for method chaining
     */
    public function set(string $key, string $value): HttpHeaders {
        $key = strtolower(trim($key));
        $this->headers[trim($key)] = $value;
        return $this;
    }

    /**
     * Retrieves a header value by its key (case-insensitive).
     * 
     * @param string $key The header name to retrieve
     * @param string|null $default Default value to return if header is not found
     * @return string|null The header value, or null if not found
     */
    public function get(string $key, ?string $default = null): ?string {
        $key = strtolower(trim($key));
        return $this->headers[$key] ?? $default;
    }

    /**
     * Removes a header by its key (case-insensitive).
     * 
     * @param string $key The header name to remove
     * @return HttpHeaders The current HttpHeaders instance for method chaining
     */
    public function remove(string $key): HttpHeaders {
        $key = strtolower(trim($key));
        unset($this->headers[$key]);
        return $this;
    }

    /**
     * Clears all headers from the collection.
     * 
     * @return HttpHeaders The current HttpHeaders instance for method chaining
     */
    public function clear(): HttpHeaders {
        $this->headers = [];
        return $this;
    }

    /**
     * Returns all headers as an associative array.
     * 
     * @return array<string, string> All headers
     */
    public function all(): array {
        return $this->headers;
    }

    /**
     * Checks if a header exists (case-insensitive).
     * 
     * @param string $key The header name to check
     * @return bool True if the header exists, false otherwise
     */
    public function has(string $key): bool {
        $key = strtolower(trim($key));
        return isset($this->headers[$key]);
    }

    /**
     * Returns the current element for iterator implementation.
     * 
     * @return mixed The current header value
     */
    public function current(): mixed {
        return current($this->headers);
    }

    /**
     * Returns the current key for iterator implementation.
     * 
     * @return mixed The current header key
     */
    public function key(): mixed {
        return key($this->headers);
    }

    /**
     * Moves the internal pointer to the next element for iterator implementation.
     */
    public function next(): void {
        next($this->headers);
    }

    /**
     * Resets the internal pointer to the first element for iterator implementation.
     */
    public function rewind(): void {
        reset($this->headers);
    }

    /**
     * Checks if the current position is valid for iterator implementation.
     * 
     * @return bool True if the current position is valid, false otherwise
     */
    public function valid(): bool {
        return current($this->headers) !== false;
    }
}