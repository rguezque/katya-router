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
 * Throws a exception when an argument don't exists or wasn't found
 */
class MissingArgumentException extends Exception {
    public function __construct(string $message, int $code = HttpStatus::HTTP_INTERNAL_SERVER_ERROR, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

?>