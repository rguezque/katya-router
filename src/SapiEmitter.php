<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Get and process the response
 * 
 * @static emit(Response $response) Send the response
 */
class SapiEmitter extends Response {

    /**
     * Send the response
     * 
     * @return true
     */
    public static function emit(Response $response): true {
        http_response_code($response->status_code);
        // Send the headers
        if(!headers_sent()) {
            $response->headers->rewind();
            while($response->headers->valid()) {
                $key = $response->headers->key();
                $value = $response->headers->current();
                header("$key: $value");
                $response->headers->next();
            }
        }

        // Output the body
        $response->body->rewind();
        echo $response->body->getContents();
        return true;
    }
}

?>