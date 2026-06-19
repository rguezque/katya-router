<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Closure;
use rguezque\Exceptions\UnsupportedRequestMethodException;
use rguezque\Interfaces\MiddlewareInterface;
use rguezque\MiddlewareTrait;

use function rguezque\functions\str_path;

/**
 * Routes group
 * 
 * This class allows you to define a group of routes with a common prefix.
 * You can define routes using different HTTP methods (GET, POST, PUT, PATCH, DELETE)
 * and apply middleware (hooks) that will be executed before the route controller.
 * 
 * @method Route route(string $verb, string $path, callable $controller)
 * @method Route get(string $path, callable $controller)
 * @method Route post(string $path, callable $controller)
 * @method Route put(string $path, callable $controller)
 * @method Route patch(string $path, callable $controller)
 * @method Route delete(string $path, callable $controller)
 * @method Group before(MiddlewareInterface $middleware)
 * @method Group useServices(string ...$names)
 */
class Group {

    use MiddlewareTrait;

    /**
     * Router object
     * 
     * @var Katya
     */
    private Katya $router;

    /**
     * Prefix for route group
     * 
     * @var string
     */
    private string $prefix;

    /**
     * Closure with routes group definition
     * 
     * @var Closure
     */
    private Closure $closure;

    /**
     * List of lot of services to use for this routes group
     * 
     * @var string[]
     */
    private array $onlyuse = [];

    /**
     * Create route group
     * 
     * @param string $prefix Route group prefix
     * @param Closure $closure Group definition
     * @param Katya $router Router object
     */
    public function __construct(string $prefix, Closure $closure, Katya $router) {
        $this->prefix = str_path($prefix);
        $this->closure = $closure;
        $this->router = $router;
    }

    /**
     * Route definition
     * 
     * @param string $verb The allowed route http method
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function route(string $verb, string $path, callable $controller): Route {
        $route = $this->router->route($verb, $this->prefix.$path, $controller);
        return $this->applyGroupSettings($route);
    }

    /**
     * Shortcut to add route with GET method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function get(string $path, callable $controller): Route {
        $route = $this->router->get($this->prefix.$path, $controller);
        return $this->applyGroupSettings($route);
    }

    /**
     * Shortcut to add route with POST method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function post(string $path, callable $controller): Route {
        $route = $this->router->post($this->prefix.$path, $controller);
        return $this->applyGroupSettings($route);
    }

    /**
     * Shortcut to add route with PUT method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function put(string $path, callable $controller): Route {
        $route = $this->router->put($this->prefix.$path, $controller);
        return $this->applyGroupSettings($route);
    }

    /**
     * Shortcut to add route with PATCH method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function patch(string $path, callable $controller): Route {
        $route = $this->router->patch($this->prefix.$path, $controller);
        return $this->applyGroupSettings($route);
    }

    /**
     * Shortcut to add route with DELETE method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function delete(string $path, callable $controller): Route {
        $route = $this->router->delete($this->prefix.$path, $controller);
        return $this->applyGroupSettings($route);
    }

    /**
     * Specify the services to use in this route
     * 
     * @param string ...$names Service names separated by comma
     * @return Group
     */
    public function useServices(string ...$names): Group {
        $this->onlyuse = $names;
        return $this;
    }

    /**
     * Allow exec the class like a function
     */
    public function __invoke() {
        ($this->closure)($this);
    }

    /**
     * Apply group settings (middleware and services) to a route.
     * 
     * @param Route $route The Route object
     * @return Route The processed Route object
     */
    private function applyGroupSettings(Route $route): Route {
        if ([] !== $this->before) {
            foreach($this->before as $middleware) {
                $route->before($middleware);
            }
        }

        if ([] !== $this->onlyuse) {
            $route->useServices(...$this->onlyuse);
        }
        
        return $route;
    }
}