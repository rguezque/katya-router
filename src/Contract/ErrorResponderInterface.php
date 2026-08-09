<?php

declare(strict_types=1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezique
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\Contract;

use rguezque\ErrorHandling\ErrorHandlerConfig;
use Throwable;

/**
 * Contract for rendering/responding to errors.
 */
interface ErrorResponderInterface
{
    /**
     * Respond to an uncaught exception.
     *
     * @param Throwable $exception
     * @param ErrorHandlerConfig $config
     * @return void
     */
    public function respond(Throwable $exception, ErrorHandlerConfig $config): void;
}
