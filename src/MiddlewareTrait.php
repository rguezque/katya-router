<?php declare(strict_types=1);

namespace rguezque;

use rguezque\Interfaces\MiddlewareInterface;

/**
 * @method self before(MiddlewareInterface $middleware) Add a hook to exec before the route controller
 * @method callable getHookBefore() Return the hook
 * @method bool hasHookBefore() Return true if the route has a hook
 */
trait MiddlewareTrait {
    /**
     * Store the middlewares
     * 
     * @var array
     */
    private array $before = [];

    /**
     * Add a hook to exec before the route controller
     * 
     * @param MiddlewareInterface $middleware Middleware object
     * @return self
     */
    public function before(MiddlewareInterface $middleware): self
    {
        $this->before[] = $middleware;
        return $this;
    }

    /**
     * Returns the middlewares array
     * 
     * @return array
     */
    public function getMiddlewares(): array {
        return $this->before;
    }

    /**
     * Returns true if the route has at least one middleware
     * 
     * @return bool
     */
    public function hasMiddlewares(): bool {
        return [] !== $this->before;
    }
}
