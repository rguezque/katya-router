<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent a redirection
 * 
 * This class extends the Response class to provide a specific implementation for redirects
 */
class RedirectResponse extends Response {
    public function __construct(string $location, int $status_code = HttpStatus::HTTP_FOUND) {
        parent::__construct(status_code: $status_code, headers: ['Location' => $location]);
    }
}

?>