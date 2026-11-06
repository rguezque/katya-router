<?php

declare(strict_types=1);

namespace Tests;

use ErrorException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use rguezque\Contract\ErrorLoggerInterface;
use rguezque\Contract\ErrorResponderInterface;
use rguezque\ErrorHandling\EnvironmentMode;
use rguezque\ErrorHandling\ErrorHandler;
use rguezque\ErrorHandling\ErrorHandlerConfig;
use rguezque\ErrorHandling\FileErrorLogger;
use rguezque\ErrorHandling\JsonErrorResponder;
use rguezque\ErrorHandling\Logger;
use Throwable;

final class ErrorHandlingTest extends TestCase
{
    private string $root;
    private string $logDirectory;
    private array $files = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/katya-errors-' . bin2hex(random_bytes(6));
        $this->logDirectory = $this->root . '/logs';
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->logDirectory)) {
            rmdir($this->logDirectory);
        }

        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testEnvironmentModeParsingAndPredicates(): void
    {
        $development = EnvironmentMode::fromString(' DEVELOPMENT ');
        self::assertSame(EnvironmentMode::Development, $development);
        self::assertTrue($development->isDevelopment());
        self::assertFalse($development->isProduction());
        self::assertTrue(EnvironmentMode::Production->isProduction());

        $this->expectException(\InvalidArgumentException::class);
        EnvironmentMode::fromString('testing');
    }

    public function testConfigAppliesModeDefaultsBooleanOverridesAndLogPath(): void
    {
        $config = ErrorHandlerConfig::fromArray([
            'mode' => 'development',
            'debug' => 'true',
            'display_errors' => false,
            'log_errors' => '0',
            'log_path' => $this->root . '/logs',
            'public_message' => 'Try later',
        ]);

        self::assertSame(EnvironmentMode::Development, $config->mode);
        self::assertTrue($config->debug);
        self::assertFalse($config->display_errors);
        self::assertFalse($config->log_errors);
        self::assertSame('Try later', $config->public_message);
        self::assertSame(E_ALL, $config->error_reporting);
        self::assertSame($this->root . '/logs/php_errors.log', $config->log_file);
        self::assertDirectoryExists(dirname($config->log_file));

        $production = ErrorHandlerConfig::fromArray(['mode' => 'production']);
        self::assertFalse($production->debug);
        self::assertSame('Internal Server Error', $production->public_message);
    }

    public function testFileLoggerWritesExceptionSeverityAndCanBeDisabled(): void
    {
        $config = ErrorHandlerConfig::fromArray(['log_path' => $this->root]);
        $exception = new ErrorException('warning details', 0, E_USER_WARNING);
        (new FileErrorLogger())->log($exception, $config);

        $contents = file_get_contents($config->log_file);
        self::assertStringContainsString('E_USER_WARNING.' . E_USER_WARNING, $contents);
        self::assertStringContainsString('warning details', $contents);
        $this->files[] = $config->log_file;

        $disabled = ErrorHandlerConfig::fromArray(['log_errors' => false, 'log_path' => $this->root]);
        (new FileErrorLogger())->log(new RuntimeException('ignored'), $disabled);
        self::assertSame($contents, file_get_contents($config->log_file));
    }

    public function testLoggerFacadeUsesItsConfiguredFileLogger(): void
    {
        $config = ErrorHandlerConfig::fromArray(['log_path' => $this->root]);
        Logger::configure($config);
        Logger::logError(new RuntimeException('facade message'));
        $this->files[] = $config->log_file;

        self::assertStringContainsString('facade message', file_get_contents($config->log_file));
    }

    public function testErrorHandlerConvertsPhpErrorsAndResponderBuildsPayloads(): void
    {
        $config = ErrorHandlerConfig::fromArray(['mode' => 'development', 'debug' => true]);
        $logger = new class implements ErrorLoggerInterface {
            public function log(Throwable $exception, ErrorHandlerConfig $config): void {}
        };
        $responder = new class implements ErrorResponderInterface {
            public function respond(Throwable $exception, ErrorHandlerConfig $config): void {}
        };
        $handler = new ErrorHandler($config, $logger, $responder);

        $convertError = new ReflectionMethod($handler, 'convertErrorToException');
        $previousErrorReporting = error_reporting();
        error_reporting(E_ALL);
        try {
            $convertError->invoke($handler, E_USER_WARNING, 'converted', __FILE__, __LINE__);
            self::fail('Unsuppressed PHP errors must become ErrorException objects.');
        } catch (\ReflectionException $exception) {
            self::fail($exception->getMessage());
        } catch (\Throwable $exception) {
            self::assertInstanceOf(ErrorException::class, $exception);
            self::assertSame('converted', $exception->getMessage());
        } finally {
            error_reporting($previousErrorReporting);
        }

        $responder = new JsonErrorResponder();
        $payloadMethod = new ReflectionMethod($responder, 'payload');
        $payloadMethod->setAccessible(true);
        $payload = $payloadMethod->invoke($responder, new RuntimeException('details', 5), $config);
        self::assertSame('details', $payload['error']);
        self::assertSame(RuntimeException::class, $payload['type']);
        self::assertSame(5, $payload['code']);
        self::assertArrayHasKey('trace_string', $payload);

        $productionPayload = $payloadMethod->invoke(
            $responder,
            new RuntimeException('private details'),
            ErrorHandlerConfig::fromArray(['mode' => 'production'])
        );
        self::assertSame(['error' => 'Internal Server Error'], $productionPayload);
    }

    public function testErrorHandlerRegistersAndRestoresPhpErrorConversion(): void
    {
        $config = ErrorHandlerConfig::fromArray([
            'mode' => 'development',
            'error_reporting' => E_ALL,
            'log_errors' => false,
        ]);
        $handler = new ErrorHandler($config);
        $previousReporting = error_reporting();
        $previousDisplayErrors = ini_get('display_errors');
        $previousStartupErrors = ini_get('display_startup_errors');
        $previousLogErrors = ini_get('log_errors');
        $handler->register();

        try {
            try {
                trigger_error('registered error', E_USER_WARNING);
                self::fail('Registered PHP errors should be converted to exceptions.');
            } catch (ErrorException $exception) {
                self::assertSame('registered error', $exception->getMessage());
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
            error_reporting($previousReporting);
            ini_set('display_errors', (string) $previousDisplayErrors);
            ini_set('display_startup_errors', (string) $previousStartupErrors);
            ini_set('log_errors', (string) $previousLogErrors);
        }

        $statusCode = new ReflectionMethod(JsonErrorResponder::class, 'statusCode');
        $statusCode->setAccessible(true);
        $responder = new JsonErrorResponder();
        self::assertSame(500, $statusCode->invoke($responder, new RuntimeException('internal')));
    }
}
