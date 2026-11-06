<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use rguezque\Contract\ConnectionInterface;
use rguezque\Contract\ModelInterface;
use rguezque\Database\Connection;
use rguezque\Database\DsnParser;
use rguezque\Database\ModelRepository;
use rguezque\Database\MysqliConnection;
use rguezque\Database\PDOConnection;
use rguezque\Database\Transaction;
use rguezque\Exception\MissingArgumentException;
use RuntimeException;
use stdClass;
use Throwable;

final class DatabaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Connection::resetAutoConnection();
    }

    public function testDsnParserNormalizesDatabaseUrlAndPreservesPasswordWhitespace(): void
    {
        $params = (new DsnParser())->parse(
            'mysql://alice:p%20ass%20@db.example.test:3307/my%20database?charset=utf8&unix_socket=%2Ftmp%2Fmysql.sock'
        );

        self::assertSame([
            'driver' => 'pdomysql',
            'host' => 'db.example.test',
            'port' => 3307,
            'db_name' => 'my database',
            'charset' => 'utf8',
            'user' => 'alice',
            'password' => 'p ass ',
            'unix_socket' => '/tmp/mysql.sock',
        ], $params);
    }

    public function testDsnParserAcceptsDriverAliasesAndRejectsInvalidUrls(): void
    {
        self::assertSame('mysqli', (new DsnParser())->parse('mysqli://localhost/app')['driver']);
        self::assertSame('pdomysql', (new DsnParser())->parse('pdo_mysql://localhost/app')['driver']);

        foreach (['', 'postgres://localhost/app'] as $url) {
            try {
                (new DsnParser())->parse($url);
                self::fail('Expected invalid DSNs to be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testConnectionFactoryValidatesInputAndExposesDrivers(): void
    {
        self::assertSame(['pdomysql', 'mysqli'], Connection::getSupportedDrivers());

        try {
            Connection::create([]);
            self::fail('A driver is required.');
        } catch (MissingArgumentException) {
            self::assertTrue(true);
        }

        foreach ([
            ['driver' => 'postgres'],
            ['driver' => 'pdomysql', 'db_name' => 'bad name'],
            ['driver' => 'pdomysql', 'charset' => 'utf8;DROP'],
            ['driver' => 'mysqli', 'unix_socket' => '/tmp/mysql.sock', 'host' => 'db.example.test'],
            ['driver' => 'pdomysql', 'options' => 'invalid'],
        ] as $params) {
            try {
                Connection::create($params);
                self::fail('Expected invalid connection arguments to be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testConnectionCanCacheAnInjectedAutoConnection(): void
    {
        $connection = new class implements ConnectionInterface {};

        Connection::setAutoConnection($connection);

        self::assertSame($connection, Connection::autoConnect());
    }

    public function testTransactionReturnsCallbackResultAndRethrowsFailures(): void
    {
        $connection = new class implements ConnectionInterface {};
        $transaction = new Transaction($connection);

        self::assertSame('result', $transaction->transactional(
            static fn(ConnectionInterface $received): string => $received === $connection ? 'result' : 'wrong'
        ));

        $failure = new RuntimeException('transaction failed');
        try {
            $transaction->transactional(static function () use ($failure): never {
                throw $failure;
            });
            self::fail('The transaction must rethrow callback failures.');
        } catch (Throwable $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testModelRepositoryCreatesAndSharesRegisteredModels(): void
    {
        $connection = new class implements ConnectionInterface {};
        $repository = new ModelRepository($connection, true);

        self::assertSame($repository, $repository->register(TestModel::class));
        self::assertTrue($repository->has('\\' . TestModel::class));
        self::assertSame([TestModel::class], $repository->getRegisteredModels());
        $model = $repository->get(TestModel::class);
        self::assertSame($model, $repository->get(TestModel::class));
        self::assertSame($connection, $model->connection);

        $repository->clearSharedInstances();
        self::assertNotSame($model, $repository->get(TestModel::class));
        $repository->unregister(TestModel::class);
        self::assertFalse($repository->has(TestModel::class));

        $this->expectException(RuntimeException::class);
        $repository->get(TestModel::class);
    }

    public function testModelRepositoryRejectsInvalidModelDefinitions(): void
    {
        $repository = new ModelRepository(new class implements ConnectionInterface {});
        foreach ([stdClass::class, InvalidTestModel::class, AbstractTestModel::class] as $class) {
            try {
                $repository->register($class);
                self::fail('Invalid model classes must not be registered.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testPdoAdapterValidatesIdentifiersBeforeConnecting(): void
    {
        $pdo = (new \ReflectionClass(PDOConnection::class))->newInstanceWithoutConstructor();
        try {
            $pdo->insert('invalid table', ['name' => 'test']);
            self::fail('PDO adapter must reject invalid table identifiers.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    public function testMysqliAdapterValidatesIdentifiersBeforeConnecting(): void
    {
        if (!extension_loaded('mysqli')) {
            self::markTestSkipped('MySQLi extension is not installed.');
        }
        $mysqli = (new \ReflectionClass(MysqliConnection::class))->newInstanceWithoutConstructor();
        try {
            $mysqli->insert('invalid table', ['name' => 'test']);
            self::fail('MySQLi adapter must reject invalid table identifiers.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }
}

final class TestModel implements ModelInterface
{
    public function __construct(public ConnectionInterface $connection) {}
}

final class InvalidTestModel {}

abstract class AbstractTestModel implements ModelInterface
{
    public function __construct(ConnectionInterface $connection) {}
}
