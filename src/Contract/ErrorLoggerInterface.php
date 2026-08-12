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
 * Contract for error loggers.
 */
interface ErrorLoggerInterface
{
    /**
     * Log an exception.
     *
     * @param Throwable $exception The exception to log
     * @param ErrorHandlerConfig $config Configuration options for handler
     * @return void
     */
    public function log(Throwable $exception, ErrorHandlerConfig $config): void;
}
