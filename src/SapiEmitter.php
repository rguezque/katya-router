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
        ob_end_clean(); // Clean the output buffer to prevent any previous output from interfering with the response
        // Set the HTTP status code
        $status_code = $response->getStatusCode();
        
        // Send the headers
        if(!headers_sent()) {
            // If it's a redirection, avoid to emit the body of response and exit
            if($response instanceof RedirectResponse) {
                $location = $response->headers->get('Location');
                header("Location: $location", true, $status_code);
                exit(0);
            }

            self::emitHeaders($response->headers, $status_code);
        }

        // Output the body
        $response->body->rewind();
        echo $response->body->getContents();
    }

    /**
     * Send only HTTP headers with a status code
     * 
     * @param HttpHeaders $headers Object with the headers
     * @param int $status_code The HTTP status code
     * @return void
     */
    public static function emitHeaders(HttpHeaders $headers, int $status_code): void {
        http_response_code($status_code);

        $headers->rewind();
        while($headers->valid()) {
            $key = ucwords($headers->key(), '-');
            $replace = $key !== 'Set-Cookie';
            $value = $headers->current();
            header("$key: $value", $replace, $status_code);
            $headers->next();
        }
    }
}

?> 