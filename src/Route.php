<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
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
 * @method Closure getController() Return controller
 * @method Route before(Closure $closure) Add a hook to exec before the route controller
 * @method Closure getHookBefore() Return the hook
 * @method bool hasHookBefore() Return true if the route has a hook
 * @method Route use(string ...$names) Specify the services to use in this route
 */
class Route {

    /**
     * Route method
     * 
     * @var string
     */
    private $verb;

    /**
     * Route path
     * 
     * @var string
     */
    private $path;

    /**
     * Route controller
     * 
     * @var Closure|array
     */
    private $closure;

    /**
     * Hook before the controller
     * 
     * @var Closure
     */
    private $before = null;

    /**
     * List of lot of services to use for this route
     * 
     * @var string[]
     */
    private $onlyuse = [];
 
    /**
     * Create route
     * 
     * @param string $verb Route http method
     * @param string $path Route path
     * @param closure $closure Route controller
     */
    public function __construct(string $verb, string $path, $closure) {
        $this->verb = $verb;
        $this->path = $path;
        $this->closure = $closure;
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
     * @return Closure|array
     */
    public function getController() {
        return $this->closure;
    }

    /**
     * Add a hook to exec before the route controller
     * 
     * @param Closure $closure Hook closure
     * @return Route
     */
    public function before(Closure $closure): Route {
        $this->before = $closure;
        return $this;
    }

    /**
     * Return the hook
     * 
     * @return Closure
     */
    public function getHookBefore(): Closure {
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
    public function use(string ...$names): Route {
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