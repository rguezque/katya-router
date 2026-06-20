<?php declare(strict_types=1);

namespace rguezque;

/**
 * Represent a URI parsed from the request
 */
class Uri {
    /**
     * The URI scheme (e.g., 'http' or 'https')
     * 
     * @var string
     */
    private string $scheme = '';

    /**
     * The URI host
     * 
     * @var string
     */
    private string $host = '';

    /**
     * The URI port, or null if using the standard port for the scheme
     * 
     * @var int|null
     */
    private ?int $port = null;

    /**
     * The URI path
     * 
     * @var string
     */
    private string $path = '';

    /**
     * The URI query string (without the leading '?')
     * 
     * @var string
     */
    private string $query = '';

    /**
     * The URI fragment (without the leading '#')
     * 
     * @var string
     */
    private string $fragment = '';

    /**
     * Class constructor
     * 
     * @param array $server The $_SERVER superglobal array containing request data
     */
    public function __construct(array $server) {
        // Determine scheme
        $this->scheme = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off') ? 'https' : 'http';

        // Determine host and port
        if (isset($server['HTTP_HOST'])) {
            $hostData = explode(':', $server['HTTP_HOST']);
            $this->host = $hostData[0];
            if (isset($hostData[1])) {
                $this->port = (int) $hostData[1];
            }
        } else {
            $this->host = $server['SERVER_NAME'] ?? 'localhost';
        }

        if ($this->port === null && isset($server['SERVER_PORT'])) {
            $this->port = (int) $server['SERVER_PORT'];
        }

        // Normalize port (hide standard ports)
        if (($this->scheme === 'http' && $this->port === 80) ||
            ($this->scheme === 'https' && $this->port === 443)
        ) {
            $this->port = null;
        }

        // Parse path, query and fragment
        $requestUri = $server['REQUEST_URI'] ?? '/';
        $parsed = parse_url($requestUri) ?: [];

        $this->path = $parsed['path'] ?? '/';
        $this->query = $parsed['query'] ?? '';
        $this->fragment = $parsed['fragment'] ?? '';
    }

    /**
     * Get the URI scheme
     * 
     * @return string
     */
    public function getScheme(): string {
        return $this->scheme;
    }

    /**
     * Get the URI host
     * 
     * @return string
     */
    public function getHost(): string {
        return $this->host;
    }

    /**
     * Get the URI port
     * 
     * @return int|null
     */
    public function getPort(): ?int {
        return $this->port;
    }

    /**
     * Get the URI path
     * 
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * Get the URI query string
     * 
     * @return string
     */
    public function getQuery(): string {
        return $this->query;
    }

    /**
     * Get the URI fragment
     * 
     * @return string
     */
    public function getFragment(): string {
        return $this->fragment;
    }

    /**
     * Get the URI authority (host and port, if applicable)
     * 
     * @return string
     */
    public function getAuthority(): string {
        $authority = $this->host;
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

    /**
     * Get the string representation of the URI
     * 
     * @return string
     */
    public function __toString(): string {
        $uri = $this->scheme . '://' . $this->getAuthority() . $this->path;
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }
}
