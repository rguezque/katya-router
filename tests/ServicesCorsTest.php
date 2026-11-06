<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use rguezque\Contract\MiddlewareInterface;
use rguezque\CorsConfig;
use rguezque\CorsHandler;
use rguezque\Exception\BadNameException;
use rguezque\Exception\DuplicityException;
use rguezque\Exception\NotFoundException;
use rguezque\HttpStatus;
use rguezque\Request;
use rguezque\Response;
use rguezque\Services;

final class ServicesCorsTest extends TestCase
{
    public function testServicesRegisterResolveFilterAndRemoveEntries(): void
    {
        $services = new Services(['db' => static fn(string $name = ''): string => 'db:' . $name]);
        self::assertTrue($services->has('db'));
        self::assertSame(['db'], $services->names());
        self::assertSame(1, $services->count());
        self::assertSame('db:main', $services->db('main'));
        self::assertSame('db:', $services->db);

        $services->register('cache', static fn(): string => 'cache');
        self::assertSame(['db', 'cache'], $services->names());
        self::assertSame(['cache'], $services->filter(['cache', 'missing'])->names());
        self::assertSame($services, $services->unregister('db', 'missing'));
        self::assertFalse($services->has('db'));
        self::assertSame(1, $services->count());

        $this->expectException(NotFoundException::class);
        $services->unknown();
    }

    public function testServicesRejectInvalidDuplicateAndReservedNames(): void
    {
        try {
            new Services(['service' => 'not a closure']);
            self::fail('Expected invalid service definitions to be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $services = new Services(['db' => static fn(): object => new \stdClass()]);
        try {
            $services->register('db', static fn(): null => null);
            self::fail('Expected duplicate service names to be rejected.');
        } catch (DuplicityException) {
            self::assertTrue(true);
        }

        try {
            $services->register('bad name', static fn(): null => null);
            self::fail('Expected spaces in service names to be rejected.');
        } catch (BadNameException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $services->register('register', static fn(): null => null);
    }

    public function testCorsAllowsConfiguredOriginAndBuildsPreflightHeaders(): void
    {
        $config = (new CorsConfig([
            'allowed_headers' => ['Content-Type', 'X-Token'],
            'expose_headers' => ['X-Rate-Limit'],
            'max_age' => 600,
            'supports_credentials' => true,
        ]))->addOrigin('https://example.test/api', ['get', 'put']);

        $actual = $config->handle(self::request([
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://example.test',
        ]));
        self::assertNotFalse($actual);
        self::assertSame('https://example.test', $actual->get('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $actual->get('Vary'));
        self::assertSame('true', $actual->get('Access-Control-Allow-Credentials'));
        self::assertSame('X-Rate-Limit', $actual->get('Access-Control-Expose-Headers'));

        $preflight = $config->handlePreflight(self::request([
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://example.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type, X-Token',
        ]));
        self::assertNotFalse($preflight);
        self::assertSame('GET, PUT', $preflight->get('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type, X-Token', $preflight->get('Access-Control-Allow-Headers'));
        self::assertSame('600', $preflight->get('Access-Control-Max-Age'));
        self::assertSame(
            'Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
            $preflight->get('Vary')
        );
    }

    public function testCorsRejectsDisallowedOriginMethodAndHeaders(): void
    {
        $config = (new CorsConfig())->addOrigin('https://example.test', ['GET'], [
            'allowed_headers' => ['Content-Type'],
        ]);

        self::assertFalse($config->handle(self::request([
            'REQUEST_METHOD' => 'POST',
            'HTTP_ORIGIN' => 'https://example.test',
        ])));
        self::assertFalse($config->handle(self::request([
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://other.test',
        ])));
        self::assertFalse($config->handlePreflight(self::request([
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://example.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'X-Not-Allowed',
        ])));
        self::assertFalse($config->handle(self::request(['REQUEST_METHOD' => 'GET'])));
    }

    public function testCorsValidatesWildcardCredentialsAndOriginPatterns(): void
    {
        try {
            (new CorsConfig(['supports_credentials' => true]))->addOrigin('*');
            self::fail('A wildcard origin cannot be used with credentials.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        try {
            (new CorsConfig())->addOrigin('[');
            self::fail('Invalid regular expressions must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $config = (new CorsConfig())->addOrigin('https://.+\.example\.test', ['*']);
        $headers = $config->handle(self::request([
            'REQUEST_METHOD' => 'DELETE',
            'HTTP_ORIGIN' => 'https://api.example.test',
        ]));
        self::assertNotFalse($headers);
        self::assertSame('https://api.example.test', $headers->get('Access-Control-Allow-Origin'));
    }

    public function testCorsHandlerHandlesPreflightMergesVaryAndCanBeDisabled(): void
    {
        $handler = new CorsHandler(
            (new CorsConfig())->addOrigin('https://example.test', ['GET'])
        );
        $request = self::request([
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://example.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        self::assertTrue($handler->isPreflight($request));
        $preflight = $handler($request, static fn(): Response => new Response('unused'));
        self::assertSame(HttpStatus::HTTP_NO_CONTENT, $preflight->getStatusCode());
        self::assertSame('https://example.test', $preflight->headers->get('Access-Control-Allow-Origin'));

        $handler->resolveHeaders(self::request([
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://example.test',
        ]));
        $response = new Response('', 200, ['Vary' => 'Accept, origin']);
        self::assertSame($response, $handler->applyCorsHeaders($response));
        self::assertSame('Accept, Origin', $response->headers->get('Vary'));

        $handler->setEnabled(false);
        self::assertFalse($handler->isEnabled());
        $passed = $handler(self::request(['REQUEST_METHOD' => 'GET']), static fn(): Response => new Response('pass'));
        self::assertSame('pass', self::body($passed));
    }

    public function testFooMiddlewareAddsItsNamedParameter(): void
    {
        $middleware = new \rguezque\Foo();
        $response = $middleware(self::request(['REQUEST_METHOD' => 'GET']), static function (Request $request): Response {
            return new Response($request->getParams()->get('sentence'));
        });

        self::assertSame('foobar', self::body($response));
    }

    private static function request(array $server): Request
    {
        return new Request([], [], $server, [], [], []);
    }

    private static function body(Response $response): string
    {
        $response->body->rewind();
        return $response->body->getContents();
    }
}
