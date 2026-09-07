<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Throwable;

/**
 * Represent an HTTP response as JSON
 * 
 * This class extends the Response class to provide a specific implementation
 * for JSON responses. It sets the content type to 'application/json;charset=utf-8'
 * and allows you to specify the data, status code, and headers.
 */
final class JsonResponse extends Response {
    /**
     * Constructor
     *
     * @param mixed $data The data to be sent. Can be a valid JSON string or a serializable structure.
     * @param int $status_code The HTTP status code for the response. Default is 200 (OK).
     * @param int $json_flags Bitmask for `json_encode`
     * @throws JsonException If there is an error encoding the data to JSON.
     * @throws InvalidArgumentException When the data isn't a valid JSON string or JSON serializable.
     */
    public function __construct(mixed $data = null, int $status_code = HttpStatus::HTTP_OK, int $json_flags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) {
        $json_data = '';

        if (is_string($data) && $this->isValidJson($data)) {
            $json_data = $data;
        } elseif ($this->isJsonSerializable($data)) {
            $json_data = json_encode($data, $json_flags);
        } else {
            throw new InvalidArgumentException('The data must be a valid JSON string or a JSON serializable structure (array, object, JsonSerializable).');
        }

        parent::__construct($json_data, $status_code);
        $this->headers->set('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Validate if a string is a valid JSON format
     *
     * @param mixed $data The data to validate
     * @return bool `true` if valid JSON, `false` otherwise
     */
    protected function isValidJson(mixed $data): bool {
        if (!is_string($data) || $data === '') {
            return false;
        }

        try {
            json_decode($data, false, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException $e) {
            return false;
        }
    }

    /**
     * Check if the data is JSON serializable
     *
     * @param mixed $data The data to check
     * @return bool True if serializable, false otherwise
     */
    protected function isJsonSerializable(mixed $data): bool {
        if ($data instanceof Response) {
            return false;
        }

        return (is_array($data) || is_object($data) || $data instanceof JsonSerializable);
    }
}