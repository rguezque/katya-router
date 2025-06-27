<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Sapi Emitter
 * 
 * This class is responsible for sending the HTTP response to the client.
 * It sets the HTTP status code, sends the headers, and outputs the body
 * of the response. It is designed to work with the SAPI (Server API) environment
 * and is typically used in web applications to handle HTTP responses.
 * 
 * @static emit(Response $response) Send the response
 */
class SapiEmitter {
    /**
     * Send the response.
     * 
     * This method is responsible for sending the HTTP response
     * to the client. It sets the HTTP status code, sends the headers,
     * and outputs the body of the response.
     * 
     * @param Response $response The response object to be sent
     * @return void
     */
    public static function emit(Response $response): void {
        http_response_code($response->getStatusCode());
        // Send the headers
        if(!headers_sent()) {
            $response->headers->rewind();
            while($response->headers->valid()) {
                $key = $response->headers->key();
                $value = $response->headers->current();
                header("$key: $value", false);
                $response->headers->next();
            }
        }

        // Output the body
        $response->body->rewind();
        echo $response->body->getContents();
    }
}

?> 