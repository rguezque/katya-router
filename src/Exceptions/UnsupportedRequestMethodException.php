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
 * Throws a exception when a request method is not supported by the router.
 */
class UnsupportedRequestMethodException extends Exception {
    public function __construct(string $message, int $code = HttpStatus::HTTP_METHOD_NOT_ALLOWED, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

?>