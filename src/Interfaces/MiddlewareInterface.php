<?php declare(strict_types = 1);

namespace rguezque\Interfaces;

use rguezque\Request;
use rguezque\Response;

/**
 * This interface define the required methods and arguments for a middleware definition
 */
interface MiddlewareInterface {
    public function __invoke(Request $request, callable $next): Response;
}