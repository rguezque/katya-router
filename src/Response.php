<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent an HTTP response
 * 
 * @method void clear() Reset the initial values for response
 * @method void setStatusCode(int $code) Set the HTTP status code
 * @method int getStatusCode() Get the HTTP status code
 */
class Response {
    /**
     * HTTP status code
     * 
     * @var int
     */
    protected int $status_code;

    /**
     * HTTP headers container
     * 
     * @var HttpHeaders
     */
    public readonly HttpHeaders $headers;

    /**
     * HTTP response body
     * 
     * @var Stream
     */
    public readonly Stream $body;

    /**
     * Initialize the http response
     * 
     * @param string $content The content of http response
     * @param int $status_code The http status code of response
     * @param array $headers HTTP headers for response
     */
    public function __construct(string $content = '', int $status_code = HttpStatus::HTTP_OK, array $headers = []) {
        $this->status_code = $status_code;
        $this->headers = [] !== $headers ? new HttpHeaders($headers) : new HttpHeaders;
        $stream = new Stream(fopen('php://memory', 'r+'));
        if('' !== trim($content)) {
            $stream->write($content);
        }
        $this->body = $stream;
    }

    /**
     * Reset the initial values for response
     * 
     * @return void
     */
    public function clear(): void {
        $this->status_code = 200;
        $this->headers->clear();
        $this->body = new Stream(fopen('php://memory', 'r+'));
    }

    /**
     * Set the HTTP status code
     * 
     * @param int $code HTTP status code
     * @return void
     */
    public function setStatusCode(int $code): void {
        $this->status_code = $code;
    }

    /**
     * Get the HTTP status code
     * 
     * @return int
     */
    public function getStatusCode(): int {
        return $this->status_code;
    }

}

?>