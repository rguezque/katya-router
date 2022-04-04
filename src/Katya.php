<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Closure;
use InvalidArgumentException;
use rguezque\Exceptions\RouteNotFoundException;
use rguezque\Exceptions\UnsupportedRequestMethodException;
use UnexpectedValueException;

/**
 * Router
 * 
 * @method Route route(string $verb, string $path, Closure $closure) Route definition
 * @method Route get(string $path, Closure $closure) Shortcut to add route with GET method
 * @method Route post(string $path, Closure $closure) Shortcut to add route with POST method
 * @method Group group(string $prefix, Closure $closure) Routes group definition under a common prefix
 * @method void run(array $server, bool $json_strategy = false) Start the router
 */
class Katya {

    /**
     * Supported verbs
     * 
     * @var string[]
     */
    private const SUPPORTED_VERBS = ['GET', 'POST'];

    /**
     * Routes collection
     * 
     * @var Route[]
     */
    private $routes = [];

    /**
     * Route groups collection
     * 
     * @var array
     */
    private $groups = [];

    /**
     * Services collection
     * 
     * @var Services
     */
    private $services = null;

    /**
     * Basepath if the router lives into subdirectory
     * 
     * @var string
     */
    private $basepath;

    /**
     * Default directory for search views templates
     * 
     * @var string
     */
    public static $viewspath;

    /**
     * Configure the router options
     * 
     * @param array $options Set basepath and viewspath
     */
    public function __construct(array $options = []) {
        $this->basepath = isset($options['basepath']) ? pathformat($options['basepath']) : '';
        self::$viewspath = isset($options['viewspath']) ? trim($options['viewspath'], '/\\ ').'/' : '';
    }

    /**
     * Set services to use into controllers
     * 
     * @param Services $services Service object
     */
    public function useServices(Services $services): Katya {
        $this->services = $services;

        return $this;
    }

    /**
     * Shorcut to add route with GET method
     * 
     * @param string $path The route path
     * @param Closure $closure The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function get(string $path, Closure $closure): Route {
        $route = $this->route('GET', $path, $closure);

        return $route;
    }

    /**
     * Shorcut to add route with POST method
     * 
     * @param string $path The route path
     * @param Closure $closure The route controller
     * @return Route
     * @throws UnsupportedRequestMethodException When the http method isn't allowed
     */
    public function post(string $path, Closure $closure): Route {
        $route = $this->route('POST', $path, $closure);

        return $route;
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
        $verb = strtoupper(trim($verb));
        $path = pathformat($path);

        if(!in_array($verb, self::SUPPORTED_VERBS)) {
            throw new UnsupportedRequestMethodException(sprintf('The HTTP method %s isn\'t allowed in route definition "%s".', $verb, $path));
        }

        $new_route = new Route($verb, $path, $closure);
        $this->routes[$verb][] = $new_route;

        return $new_route;
    }

    /**
     * Routes group definition under a common prefix
     * 
     * @param string $prefix Prefix for routes group
     * @param Closure $closure The routes definition
     * @return Group
     */
    public function group(string $prefix, Closure $closure): Group {
        $new_group = new Group($prefix, $closure, $this);
        $this->groups[] = $new_group;

        return $new_group;
    }

    /**
     * Start the router
     * 
     * @param Request $request The Request object with global params
     * @return void
     * @throws UnsupportedRequestMethodException When the http method isn't allowed by router
     * @throws UnexpectedValueException When the controller don't return a result
     * @throws RouteNotFoundException When the request uri don't match any route
     */
    public function run(Request $request): void {
        static $invoke = false;

        if(!$invoke) {
            $this->processGroups();
            $this->handleRequest($request);
            $invoke = true;
        }
    }

    /**
     * Process the route groups before routing
     * 
     * @return void
     */
    private function processGroups(): void {
        if([] !== $this->groups) {
            foreach($this->groups as $group) {
                $group();
            }
        }
    }

    /**
     * Handle the request uri and start router
     * 
     * @param Request $request The Request object with global params
     * @return void
     * @throws UnsupportedRequestMethodException When the http method isn't allowed by router
     * @throws RouteNotFoundException When the request uri don't match any route
     */
    private function handleRequest(Request $request): void {
        $server = $request->getServer();
        $request_uri = $this->filterRequestUri($server['REQUEST_URI']);
        $request_method = $server['REQUEST_METHOD'];

        if(!in_array($request_method, self::SUPPORTED_VERBS)) {
            throw new UnsupportedRequestMethodException(sprintf('The HTTP method %s isn\'t supported by router.', $request_method));
        }

        // Trailing slash no matters
        $request_uri = ('/' !== $request_uri) 
        ? rtrim($request_uri, '/\\') 
        : $request_uri;

        // Select the routes collection according to the http request method
        $routes = $this->routes[$request_method] ?? [];

        foreach($routes as $route) {
            $full_path = $this->basepath.$route->getPath();
            
            if(preg_match(self::getPattern($full_path), $request_uri, $arguments)) {
                array_shift($arguments);
                list($params, $matches) = $this->filterArguments($arguments);
                $request->setParams($params);
                $request->setMatches($matches);

                $services = $this->services;

                // Filter the services for route
                if([] !== $route->getUses() && isset($services)) {
                    $services = $this->filterServices($route->getUses());
                }

                $response = new Response;

                // Exec the middleware
                if($route->hasHookBefore()) {
                    $before = $route->getHookBefore();
                    
                    $data = isset($services) 
                        ? call_user_func($before, $request, $response, $services)
                        : call_user_func($before, $request, $response);
                    
                    if(null !== $data) {
                        $request->setParam('@data', $data);
                    }
                }

                // Exec the controller
                isset($services) 
                    ? call_user_func($route->getController(), $request, $response, $services) 
                    : call_user_func($route->getController(), $request, $response);

                // Early return to end the routing
                return; 
            }
        }
        // Default exception for routes not found
        throw new RouteNotFoundException(sprintf('The request URI "%s" don\'t match any route.', $request_uri));
    }

    /**
     * Return the regex pattern for a string path
     * 
     * @param string $path String path
     * @return string
     */
    private function getPattern(string $path): string {
        $path = str_replace('/', '\/', pathformat($path));
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
        $uri = parse_url($uri, PHP_URL_PATH);
        return rawurldecode($uri);
    }

    /**
     * Filter an arguments array. Unnamed matches are pushed to a lineal array.
     * 
     * @param array $params The array to filter
     * @return array
     */
    private function filterArguments(array $params): array {
        $matches = [];

        foreach($params as $key => $item) {
            if(is_int($key)) {
                unset($params[$key]);
                $matches[] = $item;
            }
        }

        return [
            $params,
            $matches
        ];
    }

    /**
     * Keep the services defined in list, otherwise unregister
     * 
     * @param string[] $names Service names to keep
     */
    private function filterServices(array $names): Services {
        $new_service = clone $this->services;
        $names = array_diff($new_service->keys(), $names);
        $new_service->unregister(...$names);

        return $new_service;
    }

}


/**
 * Convert a string to valid path format for the router
 * 
 * @param string $value String to convert
 * @return string
 */
function pathformat(string $value): string {
    return '/'.trim(trim($value), '/\\');
}

?>