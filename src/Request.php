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
 * @method Parameters getQuery() Return the $_GET params array
 * @method Parameters getParsedBody() Return the $_POST params array
 * @method Stream getBody() This method returns a Stream object with the content of `php://input`.
 * @method Parameters getServer() Return the $_SERVER params array
 * @method Parameters getCookies() Return the $_COOKIE params array
 * @method Parameters getFiles() Return the $_FILES params array
 * @method Parameters|array getParams(int $type = Request::PARAMS_ASSOC) Get named parameters from the route
 * @method Parameters getAllHeaders() Fetches all HTTP headers from the current request
 * @method Uri getUri() Returns the URI object representing the current request URL
 * @method void setQuery(array $query) Set values for $_GET array
 * @method void setBody(array $body) Set values for $_POST array
 * @method void setServer(array $server) Set values for $_SERVER array
 * @method void setCookies(array $cookies) Set values for $_COOKIE array
 * @method void setFiles(array $files) Set values for $_FILES array
 * @method void setParams(array $params) Set values for named params array
 * @method void addParams(array $params) Add parameters to the existing named params array
 * @method string buildQuery(string $uri, array $params) Generate URL-encoded query string
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
     * @param array $query $_GET params
     * @param array $body $_POST params
     * @param array $server $_SERVER params
     * @param array $cookies $_COOKIE params
     * @param array $files $_FILES params
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
     * This method returns the $_GET params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getQuery(): Parameters {
        return $this->query_object ??= new Parameters($this->query);
    }

    /**
     * This method returns the $_POST params encapsulated into a Parameters object.
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
     * This method returns the $_SERVER params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getServer(): Parameters {
        return $this->server_object ??= new Parameters($this->server);
    }

    /**
     * This method returns the $_COOKIE params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getCookies(): Parameters {
        return $this->cookies_object ??= new Parameters($this->cookies);
    }

    /**
     * This method returns the $_FILES params encapsulated into a Parameters object.
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
     * This method retrieves all HTTP headers from the current request and returns them as a Parameters object.
     * 
     * @return Parameters
     */
    public function getAllHeaders(): Parameters {
        return $this->headers_object ??= new Parameters(getallheaders());
    }

    /**
     * Returns the URI object representing the current request URL
     */
    public function getUri(): Uri {
        return $this->uri_object ??= new Uri($this->server);
    }

    /**
     * Set values for $_GET array
     * 
     * @param array $query Array values 
     * @return void
     */
    public function setQuery(array $query): void {
        $this->query = $query;
        $this->query_object = null;
    }

    /**
     * Set values for $_POST array
     * 
     * @param array $body Array values 
     * @return void
     */
    public function setBody(array $body): void {
        $this->body = $body;
        $this->body_object = null;
    }

    /**
     * Set values for $_SERVER array
     * 
     * @param array $server Array values 
     * @return void
     */
    public function setServer(array $server): void {
        $this->server = $server;
        $this->server_object = null;
    }

    /**
     * Set values for $_COOKIE array
     * 
     * @param array $cookies Array values 
     * @return void
     */
    public function setCookies(array $cookies): void {
        $this->cookies = $cookies;
        $this->cookies_object = null;
    }

    /**
     * Set values for $_FILES array
     * 
     * @param array $files Array values 
     * @return void
     */
    public function setFiles(array $files): void {
        $this->files = $files;
        $this->files_object = null;
    }

    /**
     * Set values for named params array
     * 
     * @param array $params Array values 
     * @return void
     */
    public function setParams(array $params): void {
        $this->params = $params;
    }

    /**
     * This method adds parameters to the existing named params array.
     * @param array $params Array of parameters to add
     * @throws InvalidArgumentException When the provided parameter is not an array
     * @return void
     */
    public function addParams(array $params): void {
        $this->params = array_merge($this->params, $params);
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