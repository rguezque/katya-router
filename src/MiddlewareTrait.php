<?php declare(strict_types=1);

namespace rguezque;

use rguezque\Contract\MiddlewareInterface;

/**
 * @method self before(MiddlewareInterface $middleware) Add a middleware to exec before the route controller.
 * @method array getMiddlewares() Return the middlewares array.
 * @method bool hasMiddlewares() Return true if the router, group or route has at least one middleware.
 */
trait MiddlewareTrait {
    /**
     * Store the middlewares
     * 
     * @var array
     */
    private array $before = [];

    /**
     * Add a middleware to exec before the route controller.
     * 
     * @param MiddlewareInterface $middleware Middleware object.
     * @return self
     */
    public function before(MiddlewareInterface $middleware): self
    {
        $this->before[] = $middleware;
        return $this;
    }

    /**
     * Returns the middlewares array.
     * 
     * @return array
     */
    public function getMiddlewares(): array {
        return $this->before;
    }

    /**
     * Returns true if the route has at least one middleware.
     * 
     * @return bool
     */
    public function hasMiddlewares(): bool {
        return [] !== $this->before;
    }
}
