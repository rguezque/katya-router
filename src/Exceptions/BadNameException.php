<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\Exceptions;

use Exception;
use rguezque\HttpStatus;
use Throwable;

/**
 * Throws an exception when a property doesn't match with a specific nomenclature.
 */
class BadNameException extends Exception {
    public function __construct(string $message, int $code = HttpStatus::HTTP_NOT_FOUND, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

?>