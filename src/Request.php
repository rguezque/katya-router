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
 * Represent a request
 * 
 * @static Request fromGlobals() Create a Request object from default global params
 * @method Parameters getQuery() Return the $_GET params array
 * @method Parameters getBody() Return the $_POST params array
 * @method string|Parameters getPhpInputStream() Return a read-only stream that allows reading data from the requested body
 * @method Parameters getServer() Return the $_SERVER params array
 * @method Parameters getCookies() Return the $_COOKIE params array
 * @method Parameters getFiles() Return the $_FILES params array
 * @method Parameters getParams() Return named params array from routing
 * @method Parameters getAllHeaders() Fetches all HTTP headers from the current request
 * @method void setQuery(array $query) Set values for $_GET array
 * @method void setBody(array $body) Set values for $_POST array
 * @method void setServer(array $server) Set values for $_SERVER array
 * @method void setCookies(array $cookies) Set values for $_COOKIE array
 * @method void setFiles(array $files) Set values for $_FILES array
 * @method void setParams(array $params) Set values for named params array
 * @static string buildQuery(string $uri, array $params) Generate URL-encoded query string
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
     * Value for return raw php input stream data
     * 
     * @var int
     */
    const RAW_DATA = 4;

    /**
     * Value for parsed php input stream
     * 
     * @var int
     */
    const PARSED_STR = 5;

    /**
     * Value for apply json decode to php input stream
     * 
     * @var int
     */
    const JSON_DECODED = 6;

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
        return new Parameters($this->query);
    }

    /**
     * This method returns the $_POST params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getBody(): Parameters {
        return new Parameters($this->body);
    }

    /**
     * This method allows you to read the raw data from the request body, parse it as a query string, or decode it as JSON.
     * 
     * @param int $option Determinate format to return the stream
     * @return Parameters|string 
     * @throws InvalidArgumentException When the option is not valid
     */
    public function getPhpInputStream(int $option = Request::RAW_DATA): Parameters|string {
        $phpinputstream = file_get_contents('php://input');

        switch($option) {
            case Request::RAW_DATA:
                $result = $phpinputstream;
                break;
            case Request::PARSED_STR:
                parse_str($phpinputstream, $result);
                $result = new Parameters($result);
                break;
            case Request::JSON_DECODED: 
                $result = new Parameters(json_decode($phpinputstream, true));
                break;
            default:
                throw new InvalidArgumentException(sprintf('Invalid option: %s. Use Request::PARSED_STR, request::JSON_DECODED or Request::RAW_DATA', $option));
        }

        return $result;
    }

    /**
     * This method returns the $_SERVER params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getServer(): Parameters {
        return new Parameters($this->server);
    }

    /**
     * This method returns the $_COOKIE params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getCookies(): Parameters {
        return new Parameters($this->cookies);
    }

    /**
     * This method returns the $_FILES params encapsulated into a Parameters object.
     * 
     * @return Parameters
     */
    public function getFiles(): Parameters {
        return new Parameters($this->files);
    }

    /**
     * This method allows you to retrieve the named parameters from the route.
     * 
     * @param int $type Specifies params array type: PARAMS_ASSOC (default), PARAMS_NUM or PARAMS_BOTH
     * @return Parameters|array
     * @throws InvalidArgumentException When the argument is not a valid array type to return
     */
    public function getParams(int $type = Request::PARAMS_ASSOC): Parameters|array {
        $result = [];
        switch($type) {
            case Request::PARAMS_ASSOC:
                foreach($this->params as $key => $value) {
                    if(!is_numeric($key)) {
                        $result[$key] = $value;
                    }
                }
                return new Parameters($result);
                break;
            case Request::PARAMS_NUM:
                foreach($this->params as $key => $value) {
                    if(is_numeric($key) && is_int($key)) {
                        $result[] = $value;
                    }
                }
                return array_values($result);
                break;
            case Request::PARAMS_BOTH:
                return $this->params;
                break;
            default:
                throw new InvalidArgumentException('Invalid argument type: '.$type.'.  Use Request::PARAMS_ASSOC, Request::PARAMS_NUM or Request::PARAMS_BOTH.');
        }
    }

    /**
     * Return a named param from route
     * 
     * @return mixed
     * @deprecated Since v1.2.6
     */
    public function getParam(string $name, $default = null) {
        return $this->params[$name] ?? $default;
    }

    /**
     * This method retrieves all HTTP headers from the current request and returns them as a Parameters object.
     * 
     * @return Parameters
     */
    public function getAllHeaders(): Parameters {
        return new Parameters(getallheaders());
    }

    /**
     * Set values for $_GET array
     * 
     * @param array $query Array values 
     * @return void
     */
    public function setQuery(array $query): void {
        $this->query = $query;
    }

    /**
     * Set values for $_POST array
     * 
     * @param array $body Array values 
     * @return void
     */
    public function setBody(array $body): void {
        $this->body = $body;
    }

    /**
     * Set values for $_SERVER array
     * 
     * @param array $server Array values 
     * @return void
     */
    public function setServer(array $server): void {
        $this->server = $server;
    }

    /**
     * Set values for $_COOKIE array
     * 
     * @param array $cookies Array values 
     * @return void
     */
    public function setCookies(array $cookies): void {
        $this->cookies = $cookies;
    }

    /**
     * Set values for $_FILES array
     * 
     * @param array $files Array values 
     * @return void
     */
    public function setFiles(array $files): void {
        $this->files = $files;
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
     * This method constructs a query string from the given URI and parameters.
     * 
     * @param string $uri URI to construct query
     * @param array $params Params to construct query
     * @return string
     */
    public static function buildQuery(string $uri, array $params): string {
        return trim($uri).'?'.http_build_query($params);
    }

}

?>