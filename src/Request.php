<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;

/**
 * Represent a server request
 * 
 * @static Request fromGlobals() Create a Request object from default global params
 * @method Parameters getQuery() Return the `$_GET` params array
 * @method Parameters getParsedBody() Return the `$_POST` params array
 * @method Stream getBody() This method returns a Stream object with the content of `php://input`.
 * @method Parameters getServer() Return the `$_SERVER` params array
 * @method Parameters getCookies() Return the `$_COOKIE` params array
 * @method Parameters getFiles() Return the `$_FILES` params array
 * @method Parameters|array getParams(int $type = Request::PARAMS_ASSOC) Get named parameters from the route
 * @method Parameters getAllHeaders() Fetches all HTTP headers from the current request
 * @method string getHeaderLine(string $name, ?string $default = null) Returns the content of a specific HTTP header
 * @method Uri getUri() Returns the URI object representing the current request URL
 * @method Request withAddedHeader(string $name, string $value) Returns a cloned Request with the specified header appended
 * @method Request withQuery(array $query) Returns a cloned Request with the new `$_GET` values
 * @method Request withBody(array $body) Returns a cloned Request with the new `$_POST` values
 * @method Request withServer(array $server) Returns a cloned Request with the new `$_SERVER` values
 * @method Request withCookies(array $cookies) Returns a cloned Request with the new `$_COOKIE` values
 * @method Request withFiles(array $files) Returns a cloned Request with the new `$_FILES` values
 * @method Request withParams(array $params) Returns a cloned Request with the new named params values
 * @method Request withAddedParams(array $params) Returns a cloned Request with parameters added to the existing named params
 * @method static string buildQuery(string $uri, array $params) Generate URL-encoded query string
 */
class Request {
    /**
     * Route parameters are returned into the array having the fieldname as the array index and encapsulated into a Parameter object.
     * 
     * @var int
     */
    const PARAMS_ASSOC = 1;
    
    /**
     * Route parameters are returned into the array having an enumerated index.
     */
    const PARAMS_NUM = 2;
    
    /**
     * Route parameters are returned into the array having both a numerical index and the fieldname as the associative index and encapsulated into a Parameter object.
     * 
     * @var int
     */
    const PARAMS_BOTH = 3;

    /**
     * $_GET params
     * 
     * @var array
     */
    private array $query;

    /**
     * $_POST params
     * 
     * @var array
     */
    private array $body;

    /**
     * $_SERVER params
     * 
     * @var array
     */
    private array $server;

    /**
     * $_COOKIE params
     * 
     * @var array
     */
    private array $cookies;

    /**
     * $_FILES params
     * 
     * @var array
     */
    private array $files;

    /**
     * Named params
     * 
     * @var array
     */
    private array $params;

    // Cache properties for lazy loading
    private ?Parameters $query_object = null;
    private ?Parameters $body_object = null;
    private ?Parameters $server_object = null;
    private ?Parameters $cookies_object = null;
    private ?Parameters $files_object = null;
    private ?Parameters $headers_object = null;
    private ?Uri $uri_object = null;
    private ?string $raw_input = null;

    /**
     * This constructor initializes the Request object with the provided parameters.
     * 
     * @param array $query `$_GET` params
     * @param array $body `$_POST` params
     * @param array $server `$_SERVER` params
     * @param array $cookies `$_COOKIE` params
     * @param array $files `$_FILES` params
     * @param array $params Route params
     */
    public function __construct(
        array $query, 
        array $body, 
        array $server, 
        array $cookies, 
        array $files, 
        array $params
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->params = $params;
    }

    /**
     * This method initializes a Request object using the global PHP arrays: $_GET, $_POST, $_SERVER, $_COOKIE, and $_FILES.
     * 
     * @return Request
     */
    public static function fromGlobals() {
        return new Request(
            $_GET, 
            $_POST, 
            $_SERVER, 
            $_COOKIE, 
            $_FILES, 
            []
        );
    }

    /**
     * This method returns the `$_GET` params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getQuery(): Parameters {
        return $this->query_object ??= new Parameters($this->query);
    }

    /**
     * This method returns the `$_POST` params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getParsedBody(): Parameters {
        return $this->body_object ??= new Parameters($this->body);
    }

    /**
    * This method returns a Stream object with the content of `php://input`.
    *
    * @return Stream
    */
    public function getBody(): Stream {
        return new Stream($this->raw_input ??= file_get_contents('php://input'));
    }

    /**
     * This method returns the `$_SERVER` params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getServer(): Parameters {
        return $this->server_object ??= new Parameters($this->server);
    }

    /**
     * This method returns the `$_COOKIE` params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getCookies(): Parameters {
        return $this->cookies_object ??= new Parameters($this->cookies);
    }

    /**
     * This method returns the `$_FILES` params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getFiles(): Parameters {
        return $this->files_object ??= new Parameters($this->files);
    }

    /**
     * This method allows you to retrieve the named parameters from the route.
     * 
     * @param int $type Specifies params array type: PARAMS_ASSOC (default), PARAMS_NUM or PARAMS_BOTH
     * @return Parameters|array
     * @throws InvalidArgumentException When the argument is not a valid array type to return
     */
    public function getParams(int $type = Request::PARAMS_ASSOC): Parameters|array {
        return match($type) {
            self::PARAMS_ASSOC => new Parameters(array_filter($this->params, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY)),
            self::PARAMS_NUM => array_values(array_filter($this->params, fn($key) => is_int($key), ARRAY_FILTER_USE_KEY)),
            self::PARAMS_BOTH => $this->params,
            default => throw new InvalidArgumentException('Invalid argument type: '.$type.'. Use Request::PARAMS_ASSOC, Request::PARAMS_NUM or Request::PARAMS_BOTH.')
        };
    }

    /**
     * Fetches all HTTP headers from the current request
     * 
     * @return Parameters
     */
    public function getAllHeaders(): Parameters {
        return $this->headers_object ??= new Parameters(getallheaders());
    }

    /**
     * Returns the content of a specific HTTP header.
     *
     * @param string $name The name of the header.
     * @param string $default The default value to return if the header does not exist.
     * @return string
     */
    public function getHeaderLine(string $name, string $default = ''): string {
        return $this->getAllHeaders()->get($name, $default);
    }

    /**
     * Returns the URI object representing the current request URL
     */
    public function getUri(): Uri {
        return $this->uri_object ??= new Uri($this->server);
    }

    /**
     * Returns a cloned `Request` with the specified header appended to any existing values.
     *
     * If the header already exists, the new value is concatenated to the existing one
     * using a comma separator (as per HTTP specification for multi-value headers).
     *
     * @param string $name The name of the header.
     * @param string $value The value to append to the header.
     * @return self A cloned instance of the `Request` with the modified header.
     * @throws InvalidArgumentException If the header name is empty.
     */
    public function withAddedHeader(string $name, string $value): self {
        if ($name === '') {
            throw new InvalidArgumentException('Header name must be a non-empty string.');
        }

        $clone = clone $this;

        // Obtain the current headers Parameters object (lazy loaded)
        $headers = $clone->getAllHeaders();

        // If the header already exists, concatenate the new value with a comma
        if ($headers->has($name)) {
            $existing = $headers->get($name);
            $value = $existing . ', ' . $value;
        }

        // Update the header in the Parameters object
        $headers->set($name, $value);

        // Reassign the modified Parameters object to the clone's cache
        $clone->headers_object = $headers;

        return $clone;
    }

    /**
     * Returns a cloned Request with the new `$_GET` values.
     *
     * @param array $query Array values
     * @return self
     */
    public function withQuery(array $query): self {
        $clone = clone $this;
        $clone->query = $query;
        $clone->query_object = null;
        return $clone;
    }

    /**
     * Returns a cloned Request with the new `$_POST` values.
     *
     * @param array $body Array values
     * @return self
     */
    public function withBody(array $body): self {
        $clone = clone $this;
        $clone->body = $body;
        $clone->body_object = null;
        return $clone;
    }

    /**
     * Returns a cloned Request with the new `$_SERVER` values.
     *
     * @param array $server Array values
     * @return self
     */
    public function withServer(array $server): self {
        $clone = clone $this;
        $clone->server = $server;
        $clone->server_object = null;
        return $clone;
    }

    /**
     * Returns a cloned Request with the new `$_COOKIE` values.
     *
     * @param array $cookies Array values
     * @return self
     */
    public function withCookies(array $cookies): self {
        $clone = clone $this;
        $clone->cookies = $cookies;
        $clone->cookies_object = null;
        return $clone;
    }

    /**
     * Returns a cloned Request with the new `$_FILES` values.
     *
     * @param array $files Array values
     * @return self
     */
    public function withFiles(array $files): self {
        $clone = clone $this;
        $clone->files = $files;
        $clone->files_object = null;
        return $clone;
    }

    /**
     * Returns a cloned Request with the new named params values.
     *
     * @param array $params Array values
     * @return self
     */
    public function withParams(array $params): self {
        $clone = clone $this;
        $clone->params = $params;
        return $clone;
    }

    /**
     * Returns a cloned Request with parameters added to the existing named params.
     *
     * @param array $params Array of parameters to add
     * @return self
     */
    public function withAddedParams(array $params): self {
        $clone = clone $this;
        $clone->params = array_merge($clone->params, $params);
        return $clone;
    }

    /**
     * This method constructs a query string from the given URI and parameters.
     * 
     * @param string $uri URI to construct query
     * @param array $params Params to construct query
     * @return string
     */
    public static function buildQuery(string $uri, array $params): string {
        $query = http_build_query($params);
        return $query === '' ? trim($uri) : trim($uri) . '?' . $query;
    }

}