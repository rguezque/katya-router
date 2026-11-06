<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use rguezque\HtmlResponse;
use rguezque\HttpHeaders;
use rguezque\HttpStatus;
use rguezque\JsonResponse;
use rguezque\Parameters;
use rguezque\RedirectResponse;
use rguezque\Request;
use rguezque\Response;
use rguezque\Stream;
use rguezque\Uri;

final class HttpComponentsTest extends TestCase
{
    public function testParametersSupportsNestedValuesAndBagOperations(): void
    {
        $parameters = new Parameters(['name' => ' Ada ', 'nested' => ['enabled' => true], 'empty' => null]);

        self::assertSame(' Ada ', $parameters->get(' name '));
        self::assertTrue($parameters->get('nested')->get('enabled'));
        self::assertSame('fallback', $parameters->get('missing', 'fallback'));
        self::assertTrue($parameters->has('name'));
        self::assertFalse($parameters->valid('empty'));
        self::assertSame('string', $parameters->gettype('name'));
        self::assertSame(['name', 'nested', 'empty'], $parameters->keys());
        self::assertSame(3, $parameters->count());
        self::assertSame(['name' => ' Ada ', 'nested' => ['enabled' => true], 'empty' => null], $parameters->jsonSerialize());

        $parameters->set(' new ', 5);
        $parameters->remove('new');
        self::assertFalse($parameters->has('new'));
        $parameters->clear();
        self::assertSame([], $parameters->all());
    }

    public function testRequestProvidesParametersCloningAndSafeMethodAndUri(): void
    {
        $request = new Request(
            ['page' => 2],
            ['title' => 'post'],
            ['REQUEST_METHOD' => 'post', 'REQUEST_URI' => "items/7?x=1\x00"],
            ['session' => 'abc'],
            ['upload' => ['name' => 'file.txt']],
            ['id' => '7', 0 => 'first']
        );
        $withQuery = $request->withQuery(['page' => 3])
            ->withBody(['title' => 'updated'])
            ->withCookies(['session' => 'new'])
            ->withFiles([])
            ->withAddedParams(['extra' => 'yes']);

        self::assertSame(2, $request->getQuery()->get('page'));
        self::assertSame(3, $withQuery->getQuery()->get('page'));
        self::assertSame('post', $request->getParsedBody()->get('title'));
        self::assertSame('updated', $withQuery->getParsedBody()->get('title'));
        self::assertSame('abc', $request->getCookies()->get('session'));
        self::assertSame('new', $withQuery->getCookies()->get('session'));
        self::assertSame([], $withQuery->getFiles()->all());
        self::assertSame('POST', $request->getRequestMethod());
        self::assertSame('/items/7?x=1', $request->getRequestUri());
        self::assertSame(['id' => '7'], $request->getParams()->all());
        self::assertSame(['first'], $request->getParams(Request::PARAMS_NUM));
        self::assertSame(['id' => '7', 0 => 'first'], $request->getParams(Request::PARAMS_BOTH));
        self::assertSame('items?a=1', Request::buildQuery(' items ', ['a' => 1]));
    }

    public function testRequestNormalizesUnknownMethodsAndSupportsMethodOverride(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'TRACE'], [], [], []);
        self::assertSame('GET', $request->getRequestMethod());

        $overridden = $request->withServer(['REQUEST_METHOD' => 'POST'])
            ->withAddedHeader('X-HTTP-Method-Override', ' patch ');
        self::assertSame('PATCH', $overridden->getRequestMethod());
        self::assertSame('TRACE', $request->getServer()->get('REQUEST_METHOD'));
        $this->expectException(InvalidArgumentException::class);
        $request->getParams(999);
    }

    public function testRequestHeaderCloningAndQueryStringWithoutParams(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET'], [], [], []);
        $changed = $request->withAddedHeader('X-Test', 'one')->withAddedHeader('X-Test', 'two');

        self::assertSame('one, two', $changed->getHeaderLine('X-Test'));
        self::assertSame('', $request->getHeaderLine('X-Test'));
        self::assertSame('/path', Request::buildQuery('/path', []));
        $this->expectException(InvalidArgumentException::class);
        $request->withAddedHeader('', 'value');
    }

    public function testUriParsesAuthorityPathQueryAndFragment(): void
    {
        $uri = new Uri([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.test:8443',
            'SERVER_PORT' => '8443',
            'REQUEST_URI' => '/a/b?x=1#top',
        ]);

        self::assertSame('https', $uri->getScheme());
        self::assertSame('localhost', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('/a/b', $uri->getPath());
        self::assertSame('x=1', $uri->getQuery());
        self::assertSame('top', $uri->getFragment());
        self::assertSame('localhost:8443', $uri->getAuthority());
        self::assertSame('https://localhost:8443/a/b?x=1#top', (string) $uri);
    }

    public function testUriNormalizesStandardPortsAndMissingRequestUri(): void
    {
        $uri = new Uri(['SERVER_PORT' => '80', 'SERVER_NAME' => 'example.test']);

        self::assertNull($uri->getPort());
        self::assertSame('/', $uri->getPath());
        self::assertSame('http://localhost/', (string) $uri);
    }

    public function testHeadersAreCaseInsensitiveIterableAndChainable(): void
    {
        $headers = new HttpHeaders(['Content-Type' => 'text/plain']);
        self::assertSame($headers, $headers->set(' x-test ', 'yes'));
        self::assertSame('yes', $headers->get('X-Test'));
        self::assertTrue($headers->has('X-TEST'));
        self::assertSame(['content-type' => 'text/plain', 'x-test' => 'yes'], $headers->all());
        self::assertSame(['content-type' => 'text/plain', 'x-test' => 'yes'], iterator_to_array($headers));
        self::assertSame($headers, $headers->remove('CONTENT-TYPE'));
        $headers->clear();
        self::assertSame([], $headers->all());
    }

    public function testStreamReadsWritesSeeksAndDetachesResource(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        self::assertSame(5, $stream->write('hello'));
        self::assertSame(5, $stream->getSize());
        $stream->rewind();
        self::assertSame('he', $stream->read(2));
        self::assertSame(2, $stream->tell());
        self::assertSame('llo', $stream->getContents());
        $stream->rewind();
        self::assertSame('hello', (string) $stream);
        $resource = $stream->detach();
        self::assertIsResource($resource);
        fclose($resource);
    }

    public function testResponseCanBeResetAndTypedResponsesSetHeaders(): void
    {
        $response = new Response('body', 201, ['X-Test' => 'yes']);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('body', self::responseBody($response));
        $response->setStatusCode(202);
        $response->clear();
        self::assertSame(HttpStatus::HTTP_OK, $response->getStatusCode());
        self::assertSame('', self::responseBody($response));
        self::assertSame([], $response->headers->all());

        $html = new HtmlResponse('<p>ok</p>');
        self::assertSame('text/html;charset=utf-8', $html->headers->get('Content-Type'));
        $redirect = new RedirectResponse('/new');
        self::assertSame(HttpStatus::HTTP_SEE_OTHER, $redirect->getStatusCode());
        self::assertSame('/new', $redirect->headers->get('Location'));
        $this->expectException(InvalidArgumentException::class);
        new RedirectResponse('/new', 200);
    }

    public function testJsonResponseEncodesDataAndRejectsUnsupportedValues(): void
    {
        $response = new JsonResponse(['answer' => 42], 201);
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame(['answer' => 42], json_decode(self::responseBody($response), true));
        self::assertSame('{"ok":true}', self::responseBody(new JsonResponse('{"ok":true}')));

        $this->expectException(InvalidArgumentException::class);
        new JsonResponse('not-json');
    }

    public function testJsonResponseRejectsResponseObjectsAsSerializableData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JsonResponse(new Response());
    }

    private static function responseBody(Response $response): string
    {
        $response->body->rewind();
        return $response->body->getContents();
    }
}
