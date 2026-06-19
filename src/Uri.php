<?php declare(strict_types=1);

namespace rguezque;

/**
 * Represent a URI parsed from the request
 */
class Uri {
    private string $scheme = '';
    private string $host = '';
    private ?int $port = null;
    private string $path = '';
    private string $query = '';
    private string $fragment = '';

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

    public function getScheme(): string {
        return $this->scheme;
    }
    public function getHost(): string {
        return $this->host;
    }
    public function getPort(): ?int {
        return $this->port;
    }
    public function getPath(): string {
        return $this->path;
    }
    public function getQuery(): string {
        return $this->query;
    }
    public function getFragment(): string {
        return $this->fragment;
    }

    public function getAuthority(): string {
        $authority = $this->host;
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }

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
