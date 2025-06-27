<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent an HTTP response as JSON
 * 
 * This class extends the Response class to provide a specific implementation
 * for JSON responses. It sets the content type to 'application/json;charset=utf-8'
 * and allows you to specify the data, status code, and headers.
 * 
 * @throws JsonException
 */
class JsonResponse extends Response {
    public function __construct(array|string $data = '', int $status_code = HttpStatus::HTTP_OK, array $headers = []) {
        if(is_array($data) && [] !== $data) {
            $data = json_encode($data, JSON_THROW_ON_ERROR);
        }
        parent::__construct($data, $status_code, $headers);
        $this->headers->set('Content-Type', 'application/json;charset=utf-8');
    }
}

?>