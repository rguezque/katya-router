<?php declare(strict_types = 1);

namespace rguezque\Contract;

use rguezque\Request;
use rguezque\Response;

/**
 * This contract define the required methods and arguments for a middleware definition
 */
interface MiddlewareInterface {
    public function __invoke(Request $request, callable $next): Response;
}