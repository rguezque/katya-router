<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use rguezque\Exception\BadNameException;
use rguezque\Exception\DuplicityException;
use rguezque\Exception\FileNotFoundException;
use rguezque\Exception\MissingArgumentException;
use rguezque\Exception\NotFoundException;
use rguezque\Exception\PermissionException;
use rguezque\Exception\RouteNotFoundException;
use rguezque\Exception\UnsupportedRequestMethodException;
use rguezque\Exception\ValidationException;
use rguezque\HttpStatus;
use rguezque\Request;
use rguezque\Response;
use rguezque\SapiEmitter;
use RuntimeException;

final class UtilitiesTest extends TestCase
{
    private string $jsonFile;
    private array $originalServer;

    protected function setUp(): void
    {
        $this->jsonFile = tempnam(sys_get_temp_dir(), 'katya-json-');
        file_put_contents($this->jsonFile, '{"answer":42}');
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        unlink($this->jsonFile);
        $_SERVER = $this->originalServer;
    }

    public function testPathStringEnvironmentAndCollectionHelpers(): void
    {
        self::assertSame('prefix/name/', \add_trailing_slash('prefix/name///'));
        self::assertSame('prefix/name', \remove_trailing_slash('prefix/name/'));
        self::assertSame('/name', \add_leading_slash('\\name'));
        self::assertSame('name', \remove_leading_slash('/\\name'));
        self::assertSame('App\\', \namespace_format('\\App\\'));
        self::assertSame('prefix/value', \str_prepend('value', 'prefix/', ''));
        self::assertSame('value/suffix', \str_append('value', '/', 'suffix'));
        self::assertSame('/a\\b', \str_path('\\a\\b/'));
        self::assertTrue(\equals('same', 'same'));
        self::assertFalse(\equals('same', 'different'));
        self::assertSame('John Doe', \pipe('strtolower', 'ucwords', 'trim')('  jOHn dOE  '));

        $_ENV['KATYA_TEST_ENV'] = 'configured';
        self::assertSame('configured', \env('KATYA_TEST_ENV'));
        self::assertSame('fallback', \env('KATYA_MISSING_ENV', 'fallback'));
        unset($_ENV['KATYA_TEST_ENV']);
    }

    public function testFileAndValueNormalizationHelpers(): void
    {
        self::assertSame(['answer' => 42], \json_file_get_contents($this->jsonFile));
        try {
            \json_file_get_contents($this->jsonFile . '.missing');
            self::fail('Missing JSON files must raise an exception.');
        } catch (FileNotFoundException) {
            self::assertTrue(true);
        }

        self::assertSame('value', \trimmed_string_or_null(' value '));
        self::assertNull(\trimmed_string_or_null('  '));
        self::assertSame('default', \trimmed_string_or_default([], 'default'));
        self::assertSame('  pass  ', \string_or_default('  pass  ', 'default'));
        self::assertSame(3307, \normalize_port('3307'));
        self::assertSame(3306, \normalize_port(0));
        self::assertSame('my db', \normalize_path('/my%20db/'));
        self::assertSame('2001:db8::1', \normalize_host('[2001:db8::1]'));
        self::assertSame('a/b', \decode_component('a%2Fb'));

        $this->expectException(InvalidArgumentException::class);
        \normalize_port('not-a-port');
    }

    public function testLocalhostAndFallbackIterationHelpers(): void
    {
        $_SERVER = ['REMOTE_ADDR' => '127.0.0.1', 'SERVER_NAME' => 'example.test'];
        self::assertTrue(\is_localhost());
        $_SERVER = ['REMOTE_ADDR' => '203.0.113.1', 'SERVER_NAME' => 'localhost'];
        self::assertTrue(\is_localhost());
        $_SERVER = ['REMOTE_ADDR' => '203.0.113.1', 'SERVER_NAME' => 'example.test'];
        self::assertFalse(\is_localhost());

        $seen = [];
        $fallback = false;
        \foreach_empty(
            ['first', 'second'],
            static function (string $value, int $key) use (&$seen): void {
                $seen[$key] = $value;
            },
            static function () use (&$fallback): void {
                $fallback = true;
            }
        );
        self::assertSame([0 => 'first', 1 => 'second'], $seen);
        self::assertFalse($fallback);

        \foreach_empty([], static function (): void {}, static function () use (&$fallback): void {
            $fallback = true;
        });
        self::assertTrue($fallback);
    }

    public function testExceptionTypesExposeTheirExpectedDefaultCodes(): void
    {
        $exceptions = [
            BadNameException::class => 400,
            DuplicityException::class => 409,
            FileNotFoundException::class => 404,
            MissingArgumentException::class => 422,
            NotFoundException::class => 404,
            PermissionException::class => 403,
            RouteNotFoundException::class => 404,
            UnsupportedRequestMethodException::class => 405,
            ValidationException::class => 422,
        ];

        foreach ($exceptions as $class => $code) {
            self::assertSame($code, (new $class('test'))->getCode());
        }

        self::assertSame(HttpStatus::HTTP_NOT_FOUND, 404);
    }

    public function testSapiEmitterWritesBodiesAndOmitsHeadBodies(): void
    {
        $oldCode = http_response_code();
        $oldServer = $_SERVER;
        $outputLevel = ob_get_level();

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            ob_start();
            (new SapiEmitter())->emit(new Response('emitted', 202));
            $body = ob_get_clean();
            self::assertSame('emitted', $body);
            self::assertSame(202, http_response_code());

            $_SERVER['REQUEST_METHOD'] = 'HEAD';
            ob_start();
            (new SapiEmitter())->emit(new Response('not sent'));
            self::assertSame('', ob_get_clean());
        } finally {
            while (ob_get_level() > $outputLevel) {
                ob_end_clean();
            }
            $_SERVER = $oldServer;
            if ($oldCode !== false) {
                http_response_code($oldCode);
            }
        }
    }

    public function testSapiEmitterRejectsInvalidStatusCode(): void
    {
        $emitter = new SapiEmitter();
        $oldCode = http_response_code();
        try {
            $this->expectException(InvalidArgumentException::class);
            $emitter->emit(new Response('', 99));
        } finally {
            if ($oldCode !== false) {
                http_response_code($oldCode);
            }
        }
    }

    public function testSapiEmitterRejectsHeaderInjection(): void
    {
        $emitter = new SapiEmitter();
        ob_start();
        try {
            $this->expectException(RuntimeException::class);
            $emitter->emit(new Response('', 200, ['X-Test' => "ok\r\nInjected: yes"]));
        } finally {
            ob_end_clean();
        }
    }
}
