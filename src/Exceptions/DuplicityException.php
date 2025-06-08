<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\Exceptions;

use Exception;
use rguezque\HttpStatus;
use Throwable;

/**
 * Throws an exception when exists a duplicate symbol declaration
 */
class DuplicityException extends Exception {
    public function __construct(string $message, int $code = HttpStatus::HTTP_INTERNAL_SERVER_ERROR, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

?>