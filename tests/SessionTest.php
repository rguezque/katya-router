<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use rguezque\Session;
use RuntimeException;

final class SessionTest extends TestCase
{
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function testSessionNamespacesAndSessionDataOperations(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        Session::configure('KatyaPhpUnit', ['use_cookies' => 0]);
        $session = Session::withNamespace(' phpunit ');
        self::assertSame($session, Session::withNamespace('phpunit'));
        self::assertSame('phpunit', $session->getNamespace());
        self::assertTrue(Session::exists('phpunit'));
        self::assertSame(Session::withDefault(), Session::withNamespace(Session::DEFAULT_NAMESPACE));

        $session->start();
        self::assertTrue($session->started());
        self::assertFalse($session->disabled());
        $session->set('name', 'Ada');
        $session->set('nullable', null);
        self::assertSame('Ada', $session->get('name'));
        self::assertSame('fallback', $session->get('missing', 'fallback'));
        self::assertTrue($session->has('nullable'));
        self::assertFalse($session->valid('nullable'));
        self::assertSame(['name' => 'Ada', 'nullable' => null], $session->all());
        self::assertSame(2, count($session));

        $session->name = 'Grace';
        self::assertSame('Grace', $session->name);
        self::assertTrue(isset($session->name));
        unset($session->name);
        self::assertFalse(isset($session->name));

        $session->remove('nullable');
        self::assertSame([], $session->all());
        $session->clear();
        self::assertSame([], $session->all());
        $session->save();
        self::assertFalse($session->started());
        self::assertFalse($session->destroy());
    }

    public function testSessionRejectsInvalidNamespaceAndSessionName(): void
    {
        try {
            Session::withNamespace(' ');
            self::fail('An empty namespace must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        Session::configure('invalid-name');
    }

    public function testConfiguringAnActiveSessionIsRejected(): void
    {
        Session::configure('KatyaActiveTest', ['use_cookies' => 0]);
        $session = Session::withNamespace('active-test');
        $session->start();

        try {
            Session::configure('AnotherName');
            self::fail('Active sessions cannot be reconfigured.');
        } catch (RuntimeException) {
            self::assertTrue(true);
        } finally {
            $session->destroy();
        }
    }
}
