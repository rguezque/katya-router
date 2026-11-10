<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use rguezque\Contract\MiddlewareInterface;
use rguezque\Exception\DuplicityException;
use rguezque\Exception\HaltException;
use rguezque\Exception\RouteNotFoundException;
use rguezque\Exception\UnsupportedRequestMethodException;
use rguezque\Group;
use rguezque\Katya;
use rguezque\Request;
use rguezque\Response;
use rguezque\Route;
use rguezque\Services;
use UnexpectedValueException;

final class RouteTest extends TestCase
{
    public function testRouteStoresDefinitionAndSupportsMiddlewareAndServices(): void
    {
        $controller = static fn(): Response => new Response('ok');
        $route = new Route('get', '/items', $controller);
        $middleware = new class implements MiddlewareInterface {
            public function __invoke(Request $request, callable $next): Response
            {
                return $next($request);
            }
        };

        self::assertSame('/items', $route->getPath());
        self::assertSame('get', $route->getMethod());
        self::assertSame($controller, $route->getController());
        self::assertFalse($route->hasMiddlewares());
        self::assertSame($route, $route->before($middleware));
        self::assertSame([$middleware], $route->getMiddlewares());
        self::assertTrue($route->hasMiddlewares());
        self::assertSame($route, $route->useServices('db', 'cache'));
        self::assertSame(['db', 'cache'], $route->getRouteServices());
    }

    public function testRouterMatchesParameterizedRouteAndRunsMiddlewareInOrder(): void
    {
        $router = new Katya('/');
        $events = [];
        $routeMiddleware = new class($events) implements MiddlewareInterface {
            public function __construct(private array &$events) {}

            public function __invoke(Request $request, callable $next): Response
            {
                $this->events[] = 'route-before';
                $response = $next($request);
                $this->events[] = 'route-after';
                return $response;
            }
        };
        $globalMiddleware = new class($events) implements MiddlewareInterface {
            public function __construct(private array &$events) {}

            public function __invoke(Request $request, callable $next): Response
            {
                $this->events[] = 'global-before';
                $response = $next($request);
                $this->events[] = 'global-after';
                return $response;
            }
        };

        $router->before($globalMiddleware);
        $router->get('/users/{id:\\d+}', static function (Request $request): Response {
            return new Response($request->getParams()->get('id'));
        })->before($routeMiddleware);

        $response = $router->run(self::request('GET', '/users/42/'));

        self::assertSame('42', self::responseBody($response));
        self::assertSame(['global-before', 'route-before', 'route-after', 'global-after'], $events);
    }

    public function testRouterAppliesBasePathAndEscapesLiteralRouteCharacters(): void
    {
        $router = new Katya('/app/');
        $router->get('/files/a.b', static fn(): Response => new Response('matched'));

        $response = $router->run(self::request('GET', '/app/files/a.b'));

        self::assertSame('matched', self::responseBody($response));
    }

    public function testRouteShortcutsAndMethodNormalization(): void
    {
        $router = new Katya('/');
        $routes = [
            $router->get('/get', static fn(): Response => new Response('get')),
            $router->post('/post', static fn(): Response => new Response('post')),
            $router->put('/put', static fn(): Response => new Response('put')),
            $router->patch('/patch', static fn(): Response => new Response('patch')),
            $router->delete('/delete', static fn(): Response => new Response('delete')),
            $router->route(' options ', '/options', static fn(): Response => new Response('options')),
        ];

        self::assertSame(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], array_map(
            static fn(Route $route): string => $route->getMethod(),
            $routes
        ));
        $customController = $router->route(' get ', '/custom', static fn(): Response => new Response('ok'))
            ->getController();
        self::assertSame('ok', self::responseBody($customController()));
    }

    public function testRouterRejectsUnsupportedMethodsAndDuplicateRoutes(): void
    {
        $router = new Katya('/');
        $this->expectException(UnsupportedRequestMethodException::class);
        $router->route('TRACE', '/', static fn(): Response => new Response());
    }

    public function testRouterRejectsDuplicateRoutesAtExecutionTime(): void
    {
        $router = new Katya('/');
        $controller = static fn(): Response => new Response();
        $router->get('/duplicate', $controller);
        $router->get('/duplicate', $controller);

        $this->expectException(DuplicityException::class);
        $router->run(self::request('GET', '/duplicate'));
    }

    public function testRouterReportsUnknownRoutesAndUnsupportedRequestMethods(): void
    {
        $router = new Katya('/');
        $router->get('/known', static fn(): Response => new Response());

        try {
            $router->run(self::request('GET', '/unknown'));
            self::fail('Expected an unmatched route to throw.');
        } catch (RouteNotFoundException) {
            self::assertTrue(true);
        }

        $this->expectException(UnsupportedRequestMethodException::class);
        $router->run(self::request('HEAD', '/known'));
    }

    public function testRouterRequiresControllersToReturnAResponse(): void
    {
        $router = new Katya('/');
        $router->get('/', static fn(): string => 'not a response');

        $this->expectException(UnexpectedValueException::class);
        $router->run(self::request('GET', '/'));
    }

    public function testRouterReturnsWelcomeResponseWhenNoRoutesAreRegistered(): void
    {
        $response = (new Katya('/'))->run(self::request('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No routes registered', self::responseBody($response));
    }

    public function testRouterProcessesEachGroupOnceAndAcceptsGroupsAddedLater(): void
    {
        $router = new Katya('/');
        $firstGroupRuns = 0;
        $router->group('/first', static function (Group $group) use (&$firstGroupRuns): void {
            $firstGroupRuns++;
            $group->get('/route', static fn(): Response => new Response('first'));
        });

        self::assertSame('first', self::responseBody($router->run(self::request('GET', '/first/route'))));
        self::assertSame('first', self::responseBody($router->run(self::request('GET', '/first/route'))));
        self::assertSame(1, $firstGroupRuns);

        $secondGroupRuns = 0;
        $router->group('/second', static function (Group $group) use (&$secondGroupRuns): void {
            $secondGroupRuns++;
            $group->get('/route', static fn(): Response => new Response('second'));
        });

        self::assertSame('second', self::responseBody($router->run(self::request('GET', '/second/route'))));
        self::assertSame(1, $secondGroupRuns);
    }

    public function testRouterRechecksDuplicatesAfterRoutesAreAdded(): void
    {
        $router = new Katya('/');
        $router->get('/route', static fn(): Response => new Response('first'));
        $router->run(self::request('GET', '/route'));
        $router->get('/route', static fn(): Response => new Response('duplicate'));

        $this->expectException(DuplicityException::class);
        $router->run(self::request('GET', '/route'));
    }

    public function testGroupsApplyPrefixMiddlewareAndInheritedServices(): void
    {
        $router = new Katya('/');
        $services = new Services(['db' => static fn(): string => 'database']);
        $router->setServices($services);
        $middleware = new class implements MiddlewareInterface {
            public function __invoke(Request $request, callable $next): Response
            {
                return $next($request->withAddedParams(['middleware' => 'ran']));
            }
        };
        $group = $router->group('/api', static function (Group $group): void {
            $group->get('/items', static function (Request $request, Services $services): Response {
                return new Response($request->getParams()->get('middleware') . ':' . $services->db());
            });
        });
        $group->before($middleware)->useServices('db');

        $response = $router->run(self::request('GET', '/api/items'));

        self::assertSame('ran:database', self::responseBody($response));
    }

    public function testExplicitRouteServicesOverrideGroupServices(): void
    {
        $router = new Katya('/');
        $router->setServices(new Services([
            'group' => static fn(): string => 'group',
            'route' => static fn(): string => 'route',
        ]));
        $router->group('/api', static function (Group $group): void {
            $group->useServices('group');
            $group->get('/items', static function (Request $request, Services $services): Response {
                return new Response(implode(',', $services->names()));
            })->useServices('route');
        });

        $response = $router->run(self::request('GET', '/api/items'));

        self::assertSame('route', self::responseBody($response));
    }

    public function testHaltCarriesItsResponse(): void
    {
        $response = new Response('halted');

        try {
            Katya::halt($response);
            self::fail('Expected the router to halt.');
        } catch (HaltException $exception) {
            self::assertSame($response, $exception->getResponse());
        }
    }

    private static function request(string $method, string $uri): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
        ], [], [], []);
    }

    private static function responseBody(Response $response): string
    {
        $response->body->rewind();
        return $response->body->getContents();
    }
}
