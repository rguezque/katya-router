<?php

declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Closure;
use rguezque\Exception\{
    DuplicityException,
    HaltException,
    RouteNotFoundException,
    UnsupportedRequestMethodException
};
use rguezque\MiddlewareTrait;
use UnexpectedValueException;

use function rguezque\functions\remove_trailing_slash;
use function rguezque\functions\str_path;

/**
 * Router engine
 * 
 * This class provides a simple and flexible way to define routes and handle HTTP requests.
 * It allows you to define routes with different HTTP methods (GET, POST, PUT, PATCH, DELETE)
 * and associate them with controllers (callable functions).
 * You can also group routes under a common prefix, set CORS configurations, and manage services
 * and variables to be used in controllers. See the documentation for more details.
 * 
 * @see https://github.com/rguezque/katya-router
 * @method Route route(string $verb, string $path, callable $controller) Add a route to router.
 * @method Route get(string $path, callable $controller) Shortcut to add route with GET method.
 * @method Route post(string $path, callable $controller) Shortcut to add route with POST method.
 * @method Route put(string $path, callable $controller) Shortcut to add route with PUT method.
 * @method Route patch(string $path, callable $controller) Shortcut to add route with PATCH method.
 * @method Route delete(string $path, callable $controller) Shortcut to add route with DELETE method.
 * @method Group group(string $prefix, Closure $closure) Routes group definition under a common prefix.
 * @method Katya setServices(Services $services) Set services to use into controllers.
 * @method ?Response run(Request $request) Start the router and return the response.
 * @method static never halt(Response $response) Stop the router and send a response. Throw a special exception containing a Response object.
 */
class Katya
{
    use MiddlewareTrait;

    /** @var string[] Supported HTTP request methods */
    private const SUPPORTED_VERBS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var string "GET" http method */
    const GET = 'GET';

    /** @var string "POST" http method */
    const POST = 'POST';

    /** @var string "PUT" http method */
    const PUT = 'PUT';

    /** @var string "PATCH" http method */
    const PATCH = 'PATCH';

    /** @var string "DELETE" http method */
    const DELETE = 'DELETE';

    /** @var array<string, array<string, Route>> Routes collection */
    private array $routes = [];

    /** @var Group[] Route groups collection */
    private array $groups = [];

    /** @var Services Services collection */
    private ?Services $services = null;

    /** @var string Global prefix */
    private string $basepath = '';

    /**
     * Initialize a router instance
     * 
     * @param ?string $basepath Assigns a global prefix to routes. 
     * If not defined, the router will automatically detect if it 
     * is nested in a subdirectory and use that as the prefix.
     */
    public function __construct(?string $basepath = null)
    {
        // Default router basepath
        $this->basepath = isset($basepath)
            ? str_path($basepath)
            : rtrim(str_replace(['\\', ' '], ['/', '%20'], dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    }

    /**
     * Set services to use into controllers.
     * 
     * @param Services $services Service object
     * @return Katya
     */
    public function setServices(Services $services): Katya
    {
        $this->services = $services;
        return $this;
    }

    /**
     * Shortcut to add route with GET method.
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function get(string $path, callable $controller): Route
    {
        return $this->route(self::GET, $path, $controller);
    }

    /**
     * Shortcut to add route with POST method.
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function post(string $path, callable $controller): Route
    {
        return $this->route(self::POST, $path, $controller);
    }

    /**
     * Shortcut to add route with PUT method.
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function put(string $path, callable $controller): Route
    {
        return $this->route(self::PUT, $path, $controller);
    }

    /**
     * Shortcut to add route with PATCH method.
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function patch(string $path, callable $controller): Route
    {
        return $this->route(self::PATCH, $path, $controller);
    }

    /**
     * Shortcut to add route with DELETE method.
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function delete(string $path, callable $controller): Route
    {
        return $this->route(self::DELETE, $path, $controller);
    }

    /**
     * Add a route to router.
     * 
     * @param string $verb The allowed route http method
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function route(string $verb, string $path, callable $controller): Route
    {
        $verb = strtoupper(trim($verb));
        $path = str_path($path);

        if (!in_array($verb, self::SUPPORTED_VERBS)) {
            throw new UnsupportedRequestMethodException(sprintf('The HTTP method %s isn\'t allowed in route definition "%s".', $verb, $path));
        }

        $route = new Route($verb, $path, $controller);
        $this->routes[$verb][] = $route;

        return $route;
    }

    /**
     * Routes group definition under a common prefix.
     * 
     * @param string $prefix Prefix for routes group
     * @param Closure $closure The routes definition
     * @return Group
     */
    public function group(string $prefix, Closure $closure): Group
    {
        $group = new Group(str_path($prefix), $closure, $this);
        $this->groups[] = $group;

        return $group;
    }

    /**
     * Process the route groups before routing.
     * 
     * @return void
     */
    private function processGroups(): void
    {
        foreach ($this->groups as $group) {
            $group();
        }
    }

    /**
     * Start the router.
     * 
     * @param Request $request The Request object with global params
     * @return ?Response The response from the controller, or null if the router has already been executed
     * @throws UnsupportedRequestMethodException When request method isn't supported
     * @throws UnexpectedValueException When the controller return an invalid result
     * @throws RouteNotFoundException When request uri don't match any route
     */
    public function run(Request $request): ?Response
    {
        static $invoke = false;
        // Ensures that the router is only invoked the first time
        if (!$invoke) {
            $invoke = true;

            $this->processGroups();
            $this->checkDuplicity();
            return $this->handleRequest($request);
        }

        return null;
    }

    /**
     * Handle the request uri and start router.
     * 
     * @param Request $request The Request object with global params
     * @return Response The controller response
     * @throws UnsupportedRequestMethodException When the http method isn't allowed by router
     * @throws UnexpectedValueException When the controller return an invalid result
     * @throws RouteNotFoundException When the request uri don't match any route
     */
    private function handleRequest(Request $request): Response
    {
        // Check if no routes are registered
        if (empty($this->routes)) {
            return new JsonResponse([
                'message' => 'Welcome to PHP Katya Router!',
                'status' => 'No routes registered',
                'documentation' => 'https://github.com/rguezque/katya-router',
                'hints' => [
                    'Add routes using Katya::route() or shortcuts: Katya::get(), Katya::post(), Katya::put(), Katya::patch(), Katya::delete()',
                    'Check your route configuration',
                    'Ensure controllers are properly set up'
                ]
            ]);
        }

        $request_uri = rawurldecode(strtok($request->getRequestUri(), '?'));
        $request_method = $request->getRequestMethod();

        if (!in_array($request_method, self::SUPPORTED_VERBS)) {
            throw new UnsupportedRequestMethodException(sprintf('The HTTP method %s isn\'t supported by router.', $request_method));
        }

        // Trailing slash no matters
        $request_uri = '/' !== $request_uri ? remove_trailing_slash($request_uri) : $request_uri;

        // Select the routes collection according to the http request method
        $routes = $this->routes[$request_method] ??= [];

        foreach ($routes as $route) {
            $full_path = $this->basepath . $route->getPath();

            if (preg_match($this->getPattern($full_path), $request_uri, $arguments)) {
                array_shift($arguments);
                $request = $request->withParams($arguments);

                $services = $this->services;
                // Filter the services for route
                if ([] !== $route->getRouteServices() && null !== $services) $services = $services->filter($route->getRouteServices());

                // Controller wrapped
                if (!is_null($services)) {
                    $next = fn($request) => call_user_func($route->getController(), $request, $services);
                } else {
                    $next = fn($request) => call_user_func($route->getController(), $request);
                }

                // Global middlewares (at the router level) are merged with route middlewares
                // Due to the reverse execution of the layered structure, the global middlewares are merged at the end.
                $global_middlewares = array_merge($route->getMiddlewares(), $this->getMiddlewares());

                // Build the layered structure (onion) where each new layer envelops the previous one.
                foreach ($global_middlewares as $middleware) {
                    $next = fn($request) => call_user_func($middleware, $request, $next);
                }

                // Triggers the reverse execution of the middlewares and finally the controller
                $result = call_user_func($next, $request);

                if (!$result instanceof Response) {
                    throw new UnexpectedValueException(sprintf('Controller must return a Response object, catched %s', $result));
                }

                // Early return to end the routing
                return $result;
            }
        }

        // Exception for routes not found
        throw new RouteNotFoundException(sprintf('The request URI "%s" don\'t match any route.', $request_uri));
    }

    /**
     * Stop the router and send a Response. Throw a special exception containing a Response object.
     * 
     * @param Response $response Response object
     * @return never
     * @throws HaltException Exception is always thrown to stop router flow
     */
    public static function halt(Response $response): never
    {
        throw new HaltException($response);
    }

    /**
     * Return the regex pattern for a string path.
     * 
     * @param string $path String path
     * @return string
     */
    private function getPattern(string $path): string
    {
        // Normalize path
        $path = str_path($path);

        // Replace named wildcards {name[:regex]} with named capture groups.
        // If regex is not provided, use a default that matches any segment except '/'
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][\w\-]*)(?::([^}]+))?\}#',
            function (array $m): string {
                $name = $m[1];
                $regex = isset($m[2]) && '' !== $m[2] ? trim($m[2]) : '[^/]+';
                // Escape the pattern delimiter if present in user regex
                $regex = str_replace('#', '\#', $regex);
                return '(?P<' . $name . '>' . $regex . ')';
            },
            $path
        );

        return '#^' . $pattern . '$#';
    }

    /**
     * Check for duplicate routes and throw an exception if any are found.
     *
     * @return void
     * @throws DuplicityException
     */
    private function checkDuplicity(): void
    {
        foreach ($this->routes as $method => $routes) {
            $registered_paths = [];
            foreach ($routes as $route) {
                $path = $route->getPath();

                if (in_array($path, $registered_paths, true)) {
                    throw new DuplicityException(sprintf(
                        'The route "%s" with method "%s" already exists.',
                        $path,
                        $method
                    ));
                }
                $registered_paths[] = $path;
            }
        }
    }
}
