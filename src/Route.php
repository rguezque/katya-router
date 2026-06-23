<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use rguezque\Interfaces\MiddlewareInterface;
use rguezque\MiddlewareTrait;

/**
 * Route
 * 
 * This class represents a route in the application.
 * It contains the HTTP method, path, controller, and optional hooks.
 * The controller can be a callable function, and the route can have a hook
 * that is executed before the controller is called.
 * 
 * @method string getPath() Return the route path
 * @method string getMethod() Return the route method
 * @method callable getController() Return controller
 * @method Route before(MiddlewareInterface $middleware) Add a middleware for the route
 * @method Route useServices(string ...$names) Specify the services names to use in this route
 */
class Route {

    use MiddlewareTrait;

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
     * @var callable
     */
    private $controller;

    /**
     * List of lot of services to use for this route
     * 
     * @var string[]
     */
    private array $services_names = [];
 
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
     * Specify the services to use in this route
     * 
     * @param string ...$names Service names separated by comma
     * @return Route
     */
    public function useServices(string ...$names): Route{
        $this->services_names = $names;
        return $this;
    }

    /**
     * Return the list of service names for this route
     * 
     * @return string[]
     */
    public function getRouteServices(): array {
        return $this->services_names;
    }

 }