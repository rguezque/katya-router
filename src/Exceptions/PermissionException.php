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
 * Throws an exception when a permission is denied.
 */
class PermissionException extends Exception {
    public function __construct(string $message, int $code = HttpStatus::HTTP_FORBIDDEN, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

?>