<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Closure;

/**
 * Route
 * 
 * @method string getPath() Return the route path
 * @method string getMethod() Return the route method
 * @method callable getController() Return controller
 * @method Route before(callable $callable) Add a hook to exec before the route controller
 * @method callable getHookBefore() Return the hook
 * @method bool hasHookBefore() Return true if the route has a hook
 * @method Route use(string ...$names) Specify the services to use in this route
 */
class Route {

    /**
     * Route method
     * 
     * @var string
     */
    private string $verb;

    /**
     * Route path
     * 
     * @var string
     */
    private string $path;

    /**
     * Route controller
     * 
     * @var Closure|array|string
     */
    private Closure|array|string $controller;

    /**
     * Hook before the controller
     * 
     * @var Closure
     */
    private ?Closure $before = null;

    /**
     * List of lot of services to use for this route
     * 
     * @var string[]
     */
    private array $onlyuse = [];
 
    /**
     * Create route
     * 
     * @param string $verb Route http method
     * @param string $path Route path
     * @param callable $controller Route controller
     */
    public function __construct(string $verb, string $path, callable $controller) {
        $this->verb = $verb;
        $this->path = $path;
        $this->controller = $controller;
    }

    /**
     * Return the route path
     * 
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * Return the route method
     * 
     * @return string
     */
    public function getMethod(): string {
        return $this->verb;
    }

    /**
     * Return the controller
     * 
     * @return callable
     */
    public function getController(): callable {
        return $this->controller;
    }

    /**
     * Add a hook to exec before the route controller
     * 
     * @param callable $callable Middleware before controller execution
     * @return Route
     */
    public function before(callable $callable): Route {
        $this->before = $callable;
        return $this;
    }

    /**
     * Return the hook
     * 
     * @return callable
     */
    public function getHookBefore(): callable {
        return $this->before;
    }

    /**
     * Return true if the route has a hook
     * 
     * @return bool
     */
    public function hasHookBefore(): bool {
        return null !== $this->before;
    }

    /**
     * Specify the services to use in this route
     * 
     * @param string ...$names Service names separated by comma
     * @return Route
     */
    public function useServices(string ...$names): Route {
        $this->onlyuse = $names;
        return $this;
    }

    /**
     * Return the list of service names for this route
     * 
     * @return string[]
     */
    public function getRouteServices(): array {
        return $this->onlyuse;
    }

 }

?>