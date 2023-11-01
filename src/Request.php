<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent a request
 * 
 * @method Request fromGlobals() Create a Request object from default global params
 * @method Parameters getQuery() Return the $_GET params array
 * @method Parameters getBody() Return the $_POST params array
 * @method string|Parameters getPhpInputStream() Return a read-only stream that allows reading data from the requested body
 * @method Parameters getServer() Return the $_SERVER params array
 * @method Parameters getCookies() Return the $_COOKIE params array
 * @method Parameters getFiles() Return the $_FILES params array
 * @method Parameters getParams() Return named params array from routing
 * @method mixed getParam(string $name, $default = null) Return a named param from routing
 * @method array getMatches() Return regex matches array
 * @method void setQuery(array $query) Set values for $_GET array
 * @method void setBody(array $body) Set values for $_POST array
 * @method void setServer(array $server) Set values for $_SERVER array
 * @method void setCookies(array $cookies) Set values for $_COOKIE array
 * @method void setFiles(array $files) Set values for $_FILES array
 * @method void setParams(array $params) Set values for named params array
 * @method void setParam(string $name, $value) Set a value into named params array
 * @method void unsetParam(string $name) Remove a named param
 * @method void setMatches(arrat $matches) Set values for regex matches array
 * @method string buildQuery(string $uri, array $params) Generate URL-encoded query string
 */
class Request {

    /**
     * Value for parsed php input stream
     * 
     * @var int
     */
    const PARSED_STR = 1;

    /**
     * Value for apply json decode to php input stream
     * 
     * @var int
     */
    const JSON_DECODED = 2;

    /**
     * $_GET params
     * 
     * @var array
     */
    private $query;

    /**
     * $_POST params
     * 
     * @var array
     */
    private $body;

    /**
     * $_SERVER params
     * 
     * @var array
     */
    private $server;

    /**
     * $_COOKIE params
     * 
     * @var array
     */
    private $cookies;

    /**
     * $_FILES params
     * 
     * @var array
     */
    private $files;

    /**
     * Named params
     * 
     * @var array
     */
    private $params;

    /**
     * Regular expressions matches
     * 
     * @var array
     */
    private $matches;

    /**
     * Create Request
     * 
     * @param array $query $_GET params
     * @param array $body $_POST params
     * @param array $server $_SERVER params
     * @param array $cookies $_COOKIE params
     * @param array $files $_FILES params
     * @param array $params Route params
     * @param array $matches Route regex matches
     */
    public function __construct(
        array $query, 
        array $body, 
        array $server, 
        array $cookies, 
        array $files, 
        array $params, 
        array $matches
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->params = $params;
        $this->matches = $matches;
    }

    /**
     * Create a Request object from default global params
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
            [], 
            []
        );
    }

    /**
     * Return the $_GET params array
     * 
     * @return Parameters
     */
    public function getQuery(): Parameters {
        return new Parameters($this->query);
    }

    /**
     * Return the $_POST params array
     * 
     * @return Parameters
     */
    public function getBody(): Parameters {
        return new Parameters($this->body);
    }

    /**
     * Return a read-only stream that allows reading data from the requested body
     * 
     * @param int $option Determinate format to return the stream
     * @return string|Parameters 
     */
    public function getPhpInputStream(int $option = 0) {
        $phpinputstream = file_get_contents('php://input');

        switch($option) {
            case Request::PARSED_STR:
                parse_str($phpinputstream, $result);
                $phpinputstream = new Parameters($result);
                break;
            case Request::JSON_DECODED: 
                $phpinputstream = new Parameters(json_decode($phpinputstream, true));
                break;
        }

        return $phpinputstream;
    }

    /**
     * Return the $_SERVER params array
     * 
     * @return Parameters
     */
    public function getServer(): Parameters {
        return new Parameters($this->server);
    }

    /**
     * Return the $_COOKIE params array
     * 
     * @return Parameters
     */
    public function getCookies(): Parameters {
        return new Parameters($this->cookies);
    }

    /**
     * Return the $_FILES params array
     * 
     * @return Parameters
     */
    public function getFiles(): Parameters {
        return new Parameters($this->files);
    }

    /**
     * Return named params array from route
     * 
     * @return Parameters
     */
    public function getParams(): Parameters {
        return new Parameters($this->params);
    }

    /**
     * Return a named param from route
     * 
     * @return mixed
     */
    public function getParam(string $name, $default = null) {
        return $this->params[$name] ?? $default;
    }

    /**
     * Return regex matches array
     * 
     * @return array
     */
    public function getMatches(): array {
        return $this->matches;
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
     * Set a value into named params array
     * 
     * @param string $name Param name
     * @param mixed $value Param value 
     * @return void
     */
    public function setParam(string $name, $value): void {
        $this->params[$name] = $value;
    }

    /**
     * Remove a named param
     * 
     * @param string $name Param name
     * @return void
     */
    public function unsetParam(string $name): void {
        unset($this->params[$name]);
    }

    /**
     * Set values for regex matches array
     * 
     * @param array $matches Array values 
     * @return void
     */
    public function setMatches(array $matches): void {
        $this->matches = $matches;
    }

    /**
     * Generate URL-encoded query string
     * 
     * @param string $uri URI to construct query
     * @param array $params Params to construct query
     * @return string
     */
    public static function buildQuery(string $uri, array $params): string {
        return rtrim($uri, '/\\').'/?'.http_build_query($params);
    }

}

?>