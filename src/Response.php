<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent a response
 * 
 * @method Response clear() Reset the response to default values
 * @method Response status(int $code) Set the response status
 * @method Response header(string $name, string $content) Add a http header to response
 * @method Response headers(array $headers) Add multiples http headers to response
 * @method Response write($content) Add content to the response body
 * @method void send($data) Send response content
 * @method void json($data, bool $encode = true) Send json content
 * @method void render(string $template, array $arguments = []) Response a rendered template
 * @method void redirect(string $uri) Response a redirect
 */
class Response {

    use View;

    /**
     * Response content
     * 
     * @var mixed
     */
    private $content = '';

    /**
     * Response status
     * 
     * @var int
     */
    private $status = 200;

    /**
     * Response headers
     * 
     * @var array
     */
    private $headers = [];

    /**
     * Create a response
     * 
     * @param string $content Response content
     * @param int $status Response status
     * @param array $headers Headers array
     */
    public function __construct(string $content = '', int $status = 200, array $headers = []) {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    /**
     * Set the response status
     * 
     * @param int $code Status code
     * @return Response
     */
    public function status(int $code): Response {
        $this->status = $code;
        return $this;
    }

    /**
     * Add a http header to response
     * 
     * @param string $name Header name
     * @param string $content Header content
     * @return Response
     */
    public function header(string $name, string $content): Response {
        $this->headers[trim($name)] = trim($content);
        return $this;
    }

    /**
     * Add multiples http headers to response
     * 
     * @param array $headers Associative array with headers
     * @return Response
     */
    public function headers(array $headers): Response {
        foreach($headers as $name => $content) {
            $this->header($name, $content);
        }

        return $this;
    }

    /**
     * Add content to the response body
     * 
     * @param mixed $content Content to add
     * @return Response
     */
    public function write($content): Response {
        $this->content .= $content;
        return $this;
    }

    /**
     * Send response content
     * 
     * @param mixed $content Content to response
     * @return void
     */
    public function send($content = ''): void {
        if('' !== $content) {
            $this->write($content);
        }

        if(!headers_sent()) {
            $this->sendHeaders();
        }

        echo $this->content;
    }

    /**
     * Send json content
     * 
     * @param mixed $data Response data
     * @param bool $encode If true, the data in encode to json
     * @return void
     */
    public function json($data, bool $encode = true): void {
        $this->header('Content-Type', 'application/json;charset=UTF-8');
        $data = $encode ? json_encode($data, JSON_PRETTY_PRINT) : $data;
        $this->send($data);
    }

    /**
     * Reset the response to default values
     * 
     * @return Response
     */
    public function clear(): Response {
        $this->content = '';
        $this->status = 200;
        $this->headers = [];

        return $this;
    }

    /**
     * Send the http headers
     * 
     * @return void
     */
    private function sendHeaders(): void {
        foreach($this->headers as $name => $content) {
            if(is_array($content)) {
                foreach($content as $key => $value) {
                    header(sprintf('%s: %s', $key, $value), false, $this->status);
                }
            }
            header(sprintf('%s: %s', $name, $content), false, $this->status);
        }
    }

    /**
     * Response a redirect
     * 
     * @param string $uri URI to redirect
     * @return void
     */
    public function redirect(string $uri): void {
        if(!strpos($uri, 'http://') && !strpos($uri, 'https://') && !strpos($uri, 'www.')) {
            $uri = '/'.trim($uri, '/\\');
        }

        $this->header('location', $uri)->status(302)->send();
    }

}

?>