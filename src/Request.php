<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent a request
 * 
 * @method Request fromGlobals() Create a Request object from default global params
 * @method array getQuery() Return the $_GET params array
 * @method array getBody() Return the $_POST params array
 * @method array getServer() Return the $_SERVER params array
 * @method array getCookies() Return the $_COOKIE params array
 * @method array getFiles() Return the $_FILES params array
 * @method array getParams() Return named params array from routing
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
     * @return array
     */
    public function getQuery(): array {
        return $this->query;
    }

    /**
     * Return the $_POST params array
     * 
     * @return array
     */
    public function getBody(): array {
        return $this->body;
    }

    /**
     * Return the $_SERVER params array
     * 
     * @return array
     */
    public function getServer(): array {
        return $this->server;
    }

    /**
     * Return the $_COOKIE params array
     * 
     * @return array
     */
    public function getCookies(): array {
        return $this->cookies;
    }

    /**
     * Return the $_FILES params array
     * 
     * @return array
     */
    public function getFiles(): array {
        return $this->files;
    }

    /**
     * Return named params array from route
     * 
     * @return array
     */
    public function getParams(): array {
        return $this->params;
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
    public function buildQuery(string $uri, array $params): string {
        return rtrim($uri, '/\\').'/?'.http_build_query($params);
    }

}

?>