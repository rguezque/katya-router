<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent an HTTP response
 * 
 * This class is used to create an HTTP response that can be sent back to the client.
 * It contains the HTTP status code, headers, and body of the response.
 * The response body is a Stream object that allows reading and writing data.
 * The headers are stored in an HttpHeaders object, and the status code is set to
 * the provided value or defaults to 200 (HTTP OK).
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
     * This class represents an HTTP response that can be sent back to the client.
     * 
     * The response body is a Stream object that allows reading and writing data.
     * The headers are stored in an HttpHeaders object, and the status code is set to
     * the provided value or defaults to 200 (HTTP OK).
     * 
     * @param string $content The content of http response
     * @param int $status_code The http status code of response
     * @param array $headers HTTP headers for response
     */
    public function __construct(string $content = '', int $status_code = HttpStatus::HTTP_OK, array $headers = []) {
        $this->status_code = $status_code;
        $this->headers = new HttpHeaders($headers);
        $stream = new Stream(fopen('php://memory', 'r+'));
        if('' !== trim($content)) {
            $stream->write($content);
        }
        $this->body = $stream;
    }

    /**
     * This method clears the status code, headers, and body of the response.
     * 
     * @return void
     */
    public function clear(): void {
        $this->status_code = 200;
        $this->headers->clear();
        $this->body = new Stream(fopen('php://memory', 'r+'));
    }

    /**
     * This method allows you to set the HTTP status code for the response.
     * 
     * @param int $code HTTP status code
     * @return void
     */
    public function setStatusCode(int $code): void {
        $this->status_code = $code;
    }

    /**
     * This method retrieves the current HTTP status code of the response.
     * 
     * @return int
     */
    public function getStatusCode(): int {
        return $this->status_code;
    }

}