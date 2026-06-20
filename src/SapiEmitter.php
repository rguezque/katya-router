<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use RuntimeException;

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
        if(headers_sent($file, $line)) {
            throw new RuntimeException("Headers already sent in $file on line $line.");
        }
            
        // Set the HTTP status code
        $status_code = $response->getStatusCode();
            
        // Send the headers
        http_response_code($status_code);

        // If it's a redirection, avoid to emit the body of response and exit
        if($response instanceof RedirectResponse) {
            $location = $response->headers->get('Location');
            if($location !== null) {
                header('Location: ' . $location, true, $status_code);
            }
            return;
        }

        // Send headers and body
        self::emitHeaders($response->headers);
        self::emitBody($response->body);
    }

    /**
     * Send only HTTP headers with a status code
     * 
     * @param HttpHeaders $headers Object with the headers
     * @return void
     */
    public static function emitHeaders(HttpHeaders $headers): void {
        foreach ($headers as $key => $value) {
            $key = ucwords($key, '-');
            // Las cookies no deben sobrescribirse, el resto sí
            $replace = strcasecmp($key, 'Set-Cookie') !== 0; 
            
            header("$key: $value", $replace);
        }
    }

    public static function emitBody(Stream $body) {
        $body->rewind();
        echo $body->getContents();
    }
}