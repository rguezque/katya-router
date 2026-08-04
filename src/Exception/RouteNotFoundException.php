<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\Exception;

use Throwable;

/**
 * Throws a exception when a request uri did not match any route.
 */
class RouteNotFoundException extends NotFoundException {
    public function __construct(string $message, int $code = 404, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}