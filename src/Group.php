<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Closure;

/**
 * Routes group
 * 
 * @method Route route(string $verb, string $path, Closure $closure) Route definition
 * @method Route get(string $path, Closure $closure) Shortcut to add route with GET method
 * @method Route post(string $path, Closure $closure) Shortcut to add route with POST method
 * @method Group before(Closure $closure) Add a hook to exec before each route into the group
 * @method Group use(string ...$names) Specify the services to use in this routes group
 */
class Group {

    /**
     * Router object
     * 
     * @var Katya
     */
    private $router;

    /**
     * Prefix for route group
     * 
     * @var string
     */
    private $prefix;

    /**
     * Closure with routes definition
     * 
     * @var Closure
     */
    private $closure;

    /**
     * Middleware before the controller
     * 
     * @var Closure
     */
    private $before = null;

    /**
     * List of lot of services to use for this routes group
     * 
     * @var string[]
     */
    private $onlyuse = [];

    /**
     * Create route group
     * 
     * @param string $prefix Route group prefix
     * @param Closure $closure Group definition
     * @param Katya $router Router object
     */
    public function __construct(string $prefix, Closure $closure, Katya $router) {
        $this->prefix = '/' . trim($prefix, '/\\');
        $this->closure = $closure;
        $this->router = $router;
    }

    /**
     * Route definition
     * 
     * @param string $verb The allowed route http method
     * @param string $path The route path
     * @param Closure $closure The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function route(string $verb, string $path, Closure $closure): Route {
        $route = $this->router->route($verb, $this->prefix.$path, $closure);
        
        // Set the hook
        if(null !== $this->before) {
            $route->before($this->before);
        }

        // Set the specific services to use
        if([] !== $this->onlyuse) {
            $route->use(...$this->onlyuse);
        }

        return $route;
    }

    /**
     * Shortcut to add route with GET method
     * 
     * @param string $path The route path
     * @param Closure $closure The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function get(string $path, Closure $closure): Route {
        $route = $this->router->get($this->prefix.$path, $closure);

        // Set the hook
        if(null !== $this->before) {
            $route->before($this->before);
        }

        // Set the specific services to use
        if([] !== $this->onlyuse) {
            $route->use(...$this->onlyuse);
        }

        return $route;
    }

    /**
     * Shortcut to add route with POST method
     * 
     * @param string $path The route path
     * @param Closure $closure The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function post(string $path, Closure $closure): Route {
        $route = $this->router->post($this->prefix.$path, $closure);

        // Set the hook
        if(null !== $this->before) {
            $route->before($this->before);
        }

        // Set the specific services to use
        if([] !== $this->onlyuse) {
            $route->use(...$this->onlyuse);
        }

        return $route;
    }

    /**
     * Add a hook to exec before each route into the group
     * 
     * @param Closure $closure Middleware closure
     * @return Group
     */
    public function before(Closure $closure): Group {
        $this->before = $closure;
        return $this;
    }

    /**
     * Specify the services to use in this route
     * 
     * @param string ...$names Service names separated by comma
     * @return Group
     */
    public function use(string ...$names): Group {
        $this->onlyuse = $names;
        return $this;
    }

    /**
     * Allow exec the class like a function
     */
    public function __invoke() {
        ($this->closure)($this);
    }

}

?>