<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Closure;
use rguezque\Exceptions\{
    RouteNotFoundException,
    UnsupportedRequestMethodException
};
use UnexpectedValueException;

use function rguezque\functions\remove_trailing_slash;
use function rguezque\functions\str_path;

/**
 * Router
 * 
 * This class provides a simple and flexible way to define routes and handle HTTP requests.
 * It allows you to define routes with different HTTP methods (GET, POST, PUT, PATCH, DELETE)
 * and associate them with controllers (callable functions).
 * You can also group routes under a common prefix, set CORS configurations, and manage services
 * and variables to be used in controllers. See the documentation for more details.
 * 
 * @see https://github.com/rguezque/katya-router
 * @method Route route(string $verb, string $path, callable $controller) Route definition
 * @method Route get(string $path, callable $controller) Shortcut to add route with GET method
 * @method Route post(string $path, callable $controller) Shortcut to add route with POST method
 * @method Route put(string $path, callable $controller) Shortcut to add route with PUT method
 * @method Route patch(string $path, callable $controller) Shortcut to add route with PATCH method
 * @method Route delete(string $path, callable $controller) Shortcut to add route with DELETE method
 * @method Group group(string $prefix, Closure $closure) Routes group definition under a common prefix
 * @method Katya setCors(CorsConfig $cors_config) Set the CORS configuration
 * @method Katya setServices(Services $services) Set services to use into controllers
 * @method Katya setVariables(Variables $vars) Set variables to use into controllers
 * @method ?Response run(Request $request) Start the router and return the response
 * @method void halt(Response $response) Stop the router and send the response
 */
class Katya {
    /**
     * Supported verbs
     * 
     * @var string[]
     */
    private const SUPPORTED_VERBS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * GET constant
     * 
     * @var string
     */
    const GET = 'GET';

    /**
     * POST constant
     * 
     * @var string
     */
    const POST = 'POST';

    /**
     * PUT constant
     * 
     * @var string
     */
    const PUT = 'PUT';

    /**
     * PATCH constant
     * 
     * @var string
     */
    const PATCH = 'PATCH';

    /**
     * DELETE constant
     * 
     * @var string
     */
    const DELETE = 'DELETE';

    /**
     * Routes collection
     * 
     * @var Route[]
     */
    private array $routes = [];

    /**
     * Route groups collection
     * 
     * @var array
     */
    private array $groups = [];

    /**
     * Services collection
     * 
     * @var Services
     */
    private ?Services $services = null;

    /**
     * Basepath if the router lives into subdirectory
     * 
     * @var string
     */
    private string $basepath = '';

    /**
     * Variables collection
     * 
     * @var Variables
     */
    private ?Variables $vars = null;

    /**
     * CORS configuration
     * 
     * @var CorsConfig
     */
    private ?CorsConfig $cors_config = null;

    /**
     * Initialize a router instance
     * 
     * @param ?string $basepath Set the basepath if router is nested in subdirectory
     */
    public function __construct(?string $basepath = null) {
        // Default router basepath
        $this->basepath = isset($basepath) 
            ? str_path($basepath) 
            : rtrim(str_replace(['\\', ' '], ['/', '%20'], dirname($_SERVER['SCRIPT_NAME'])), '/\\');
    }

    /**
     * Set the CORS configuration
     * 
     * @param CorsConfig $cors_config An object with the CORS definitions
     * @return Katya
     */
    public function setCors(CorsConfig $cors_config): Katya {
        $this->cors_config = $cors_config;

        return $this;
    }

    /**
     * Set services to use into controllers
     * 
     * @param Services $services Service object
     * @return Katya
     */
    public function setServices(Services $services): Katya {
        $this->services = $services;

        return $this;
    }

    /**
     * Shortcut to add route with GET method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function get(string $path, callable $controller): Route {
        $route = $this->route(self::GET, $path, $controller);

        return $route;
    }

    /**
     * Shortcut to add route with POST method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function post(string $path, callable $controller): Route {
        $route = $this->route(self::POST, $path, $controller);

        return $route;
    }

    /**
     * Shortcut to add route with PUT method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function put(string $path, callable $controller): Route {
        $route = $this->route(self::PUT, $path, $controller);

        return $route;
    }

    /**
     * Shortcut to add route with PATCH method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function patch(string $path, callable $controller): Route {
        $route = $this->route(self::PATCH, $path, $controller);

        return $route;
    }

    /**
     * Shortcut to add route with DELETE method
     * 
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function delete(string $path, callable $controller): Route {
        $route = $this->route(self::DELETE, $path, $controller);

        return $route;
    }

    /**
     * Route definition
     * 
     * @param string $verb The allowed route http method
     * @param string $path The route path
     * @param callable $controller The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http request method isn't supported
     */
    public function route(string $verb, string $path, callable $controller): Route {
        $verb = strtoupper(trim($verb));
        $path = str_path($path);

        if(!in_array($verb, self::SUPPORTED_VERBS)) {
            throw new UnsupportedRequestMethodException(sprintf('The HTTP method %s isn\'t allowed in route definition "%s".', $verb, $path));
        }

        // Set the "path" as identifier, avoiding duplicate routes. 
        // So, a route overwrite another with same route path
        return $this->routes[$verb][$path] = new Route($verb, $path, $controller);
    }

    /**
     * Routes group definition under a common prefix
     * 
     * @param string $prefix Prefix for routes group
     * @param Closure $closure The routes definition
     * @return Group
     */
    public function group(string $prefix, Closure $closure): Group {
        $group = new Group(str_path($prefix), $closure, $this);
        $this->groups[] = $group;

        return $group;
    }

    /**
     * Resolve the CORS configuration
     * 
     * @param Request $request Request object with informatión about the request origin
     * @return void
     */
    public function resolveCors(Request $request): void {
        if(null !== $this->cors_config) {
            ($this->cors_config)($request, new Response);
        }
    }

    /**
     * Process the route groups before routing
     * 
     * @return void
     */
    private function processGroups(): void {
        foreach($this->groups as $group) {
            $group();
        }
    }

    /**
     * Start the router
     * 
     * @param Request $request The Request object with global params
     * @return ?Response The controller response, or null if it has already been executed previously
     * @throws UnsupportedRequestMethodException When request method isn't supported
     * @throws UnexpectedValueException When the controller return an invalid result
     * @throws RouteNotFoundException When request uri don't match any route
     */
    public function run(Request $request): ?Response {
        static $invoke = false;

        // Ensures that the router is only invoked the first time
        if(!$invoke) {
            $this->resolveCors($request);
            $this->processGroups();
            $invoke = true;
            return $this->handleRequest($request);
        }

        return null;
    }

    /**
     * Handle the request uri and start router
     * 
     * @param Request $request The Request object with global params
     * @return Response The controller response
     * @throws UnsupportedRequestMethodException When the http method isn't allowed by router
     * @throws UnexpectedValueException When the controller return an invalid result
     * @throws RouteNotFoundException When the request uri don't match any route
     */
    private function handleRequest(Request $request): Response {
        // Check if no routes are registered
        if ([] === ($this->routes)) {
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

        $server = $request->getServer();
        $request_uri = $this->filterRequestUri($server->get('REQUEST_URI'));
        $request_method = $server->get('REQUEST_METHOD');

        if(!in_array($request_method, self::SUPPORTED_VERBS)) {
            throw new UnsupportedRequestMethodException(sprintf('The HTTP method %s isn\'t supported by router.', $request_method));
        }

        // Trailing slash no matters
        $request_uri = '/' !== $request_uri 
        ? remove_trailing_slash($request_uri) 
        : $request_uri;

        // Select the routes collection according to the http request method
        $routes = $this->routes[$request_method] ?? [];

        foreach($routes as $route) {
            $full_path = $this->basepath.$route->getPath();

            if(preg_match($this->getPattern($full_path), $request_uri, $arguments)) {
                array_shift($arguments);
                $request->setParams($arguments);

                $services = $this->services;

                // Filter the services for route
                if([] !== $route->getRouteServices() && null !== $services) {
                    $services = $this->filterServices($route->getRouteServices());
                }

                $controller_args = [$request];

                // Add services to route arguments
                if(null !== $services) {
                    array_push($controller_args, $services);
                }

                // Add variables to route arguments, if exists
                if(null !== $this->vars) {
                    array_push($controller_args, $this->vars);
                }

                $next = function(...$controller_args) use($route) {
                    return call_user_func($route->getController(), ...$controller_args);
                };

                foreach($route->getHookBefore() as $middleware) {
                    $next = function(...$controller_args) use($middleware, $next) {
                        $all_args = array_merge($controller_args, [$next]);
                        return $middleware(...$all_args);
                    };
                }

                $result = call_user_func($next, ...$controller_args);

                if(!$result instanceof Response) {
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
     * Stop the router
     * 
     * @param Response $response Response object
     */
    public static function halt(Response $response): void {
        SapiEmitter::emit($response);
        exit(0);
    }

    /**
     * Return the regex pattern for a string path
     * 
     * @param string $path String path
     * @return string
     */
    private function getPattern(string $path): string {
        $path = str_replace('/', '\/', str_path($path));
        $path = preg_replace('#{(\w+)}#', '(?<$1>\w+)', $path); // Replace wildcards
        
        return '#^'.$path.'$#i';
    }

    /**
     * Filter a URI with GET params
     * 
     * @param string $uri The URI
     * @return string
     */
    private function filterRequestUri(string $uri): string {
        return rawurldecode(parse_url($uri, PHP_URL_PATH));
    }

    /**
     * Keep the services defined in list, otherwise unregister
     * 
     * @param string[] $names Service names to keep
     */
    private function filterServices(array $names): Services {
        $filtered = clone $this->services;
        $to_remove = array_diff($filtered->keys(), $names);
        if($to_remove) {
            $filtered->unregister(...$to_remove);
        }

        return $filtered;
    }

}

?>