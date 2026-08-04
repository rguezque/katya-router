<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\Exception;

use Exception;
use Throwable;

/**
 * Throws an exception when exists a duplicate symbol declaration.
 */
class DuplicityException extends Exception {
    public function __construct(string $message, int $code = 409, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}