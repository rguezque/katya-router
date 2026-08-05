<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Countable;
use InvalidArgumentException;
use RuntimeException;
use rguezque\Interface\ArgumentsInterface;
use rguezque\Interface\BagInterface;

/**
 * Represents a PHP session.
 * This class provides methods to create, start, and manage session variables.
 * It allows you to set, get, remove, and check the existence of session variables.
 * The session variables are stored in a specific namespace, which can be customized.
 * The class implements the BagInterface and ArgumentsInterface, providing a consistent
 * interface for managing session variables.
 * 
 * @method static void configure(?string $session_name = null, array $start_options = []) Configure the session name and options before starting the session
 * @method Session withNamespace(?string $session_name = null) Create or return an instance of `Session` from the specified namespace.
 * @method Session withDefault() Create or return an instance of `Session` with the default PHP session name 'AppSession.
 * @method string getNamespace() Return the current session vars namespace.
 * @method bool exists(string $namespace) Return `true` if a namespace already exists, otherwise `false`.
 * @method void start() Starts or resume a session.
 * @method void regenerateId(bool $delete_old_session = true) Regenerates the session ID to prevent session fixation attacks.
 * @method bool started() Return true if already exists an active session, otherwise false.
 * @method bool disabled() Return true if sessions are disabled, otherwise false.
 * @method void set(string $key, mixed $value) Set or overwrite a session var.
 * @method void get(string $key, mixed $default = null) If exists, retrieve a session var by name, otherwise returns default.
 * @method array all() Retrieve all session vars in the current namespace.
 * @method bool has(string $key) Return true if exists a session var by name.
 * @method bool valid(string $key) Return true if a session var is not null and is not empty.
 * @method int count() Return the count of session vars.
 * @method void remove(string $key) Removes a session var by name.
 * @method void clear() Removes all session vars.
 * @method bool destroy() Destroy the active session.
 */
final class Session implements BagInterface, ArgumentsInterface, Countable
{
    /** @var string Default session vars namespace */
    public const DEFAULT_NAMESPACE = 'app';

    /** @var string Default session name */
    private const DEFAULT_SESSION_NAME = 'AppSession';

    /** @var array<string, Session> Store instances of `Session` */
    private static array $instances = [];

    /** @var string|null Current session name */
    private static ?string $session_name = null;

    /** @var array<string, mixed> Options for starting the session */
    private static array $start_options = [];

    /** @var string Current session vars namespace */
    private readonly string $namespace;

    /**
     * Initialize a session
     * 
     * @param string $namespace Custom session vars namespace
     */
    private function __construct(string $namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * Configure the session name and options before starting the session.
     * 
     * @param array<string, mixed> $start_options
     * @return void
     * @throws RuntimeException If the session has already been started before calling this method
     * @throws InvalidArgumentException If the session name is invalid
     */
    public static function configure(
        ?string $session_name = null,
        array $start_options = []
    ): void {
        if (self::started()) {
            throw new RuntimeException(
                'Session must be configured before starting it.'
            );
        }

        if (null !== $session_name) {
            self::assertValidSessionName($session_name);
            self::$session_name = $session_name;
        }

        self::$start_options = $start_options;
    }

    /**
     * Create or return an instance of `Session` from the specified namespace.
     * 
     * Works as a semantic constructor implementing the Factory + Multiton patterns. 
     * Allows you to create and manage different namespaces and prevents overwriting 
     * them throughout the entire application.
     * 
     * @param string $namespace Namespace for session vars
     * @return Session
     * @throws InvalidArgumentException If the namespace is an empty string
     */
    public static function withNamespace(string $namespace): self
    {
        $namespace = trim($namespace);

        if ('' === $namespace) {
            throw new InvalidArgumentException(
                'The namespace is not valid. Empty string is NOT allowed.'
            );
        }

        return self::$instances[$namespace] ??= new self($namespace);
    }

    /**
     * Create or return an instance of `Session` with the default PHP session name 'AppSession.
     * 
     * @return Session
     */
    public static function withDefault(): self
    {
        return self::withNamespace(self::DEFAULT_NAMESPACE);
    }

    /**
     * Return `true` if a namespace already exists, otherwise `false`.
     * 
     * @param string $namespace The session vars namespace to check if it already exists
     * @return bool
     */
    public static function exists(string $namespace): bool
    {
        return isset(self::$instances[trim($namespace)]);
    }

    /**
     * Return the current session vars namespace.
     * 
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * Starts or resume a session.
     * 
     * @return void
     * @throws RuntimeException If the session cannot be started or headers have already been sent
     * @throws InvalidArgumentException If the session name is invalid
     */
    public function start(): void
    {
        if ($this->started()) {
            return;
        }

        if ($this->disabled()) {
            throw new RuntimeException('PHP sessions are disabled.');
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf(
                'Cannot start session after headers have been sent (%s:%d).',
                $file,
                $line
            ));
        }

        $session_name = self::$session_name ?? self::DEFAULT_SESSION_NAME;

        self::assertValidSessionName($session_name);
        session_name($session_name);

        $options = self::$start_options;

        if ([] === $options) {
            $options = [
                'use_strict_mode' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'cookie_secure' => self::isHttps(),
            ];
        }

        if (!session_start($options)) {
            throw new RuntimeException('Unable to start session.');
        }
    }

    /**
     * Return `true` if already exists an active session, otherwise `false`.
     * 
     * @return bool
     */
    public function started(): bool
    {
        return PHP_SESSION_ACTIVE === session_status();
    }

    /**
     * Return `true` if sessions are disabled, otherwise `false`.
     * 
     * @return bool
     */
    public function disabled(): bool {
        return PHP_SESSION_DISABLED === session_status();
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     * Should be called ONLY on authentication events (login/logout).
     * 
     * @param bool $delete_old_session Flag for indicate regenerate session id
     * @return void
     * @throws RuntimeException If the session ID cannot be regenerated
     */
    public function regenerateId(bool $delete_old_session = true): void
    {
        if (!$this->started()) {
            return;
        }

        if (!session_regenerate_id($delete_old_session)) {
            throw new RuntimeException('Unable to regenerate session ID.');
        }
    }

    /**
     * Set or overwrite a session var.
     * 
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->start();

        $_SESSION[$this->namespace][$key] = $value;
    }

    /**
     * If exists, retrieve a session var by name, otherwise returns default.
     * 
     * @param string $key Variable name
     * @param mixed $default Default value to return
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        $data = $_SESSION[$this->namespace] ?? [];

        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }

        return $default;
    }

    /**
     * Retrieve all session vars in the current namespace.
     * 
     * @return array
     */
    public function all(): array
    {
        $this->start();

        $data = $_SESSION[$this->namespace] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Return true if exists a session var by name.
     * 
     * @param string $key Variable name
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->start();

        $data = $_SESSION[$this->namespace] ?? null;

        return is_array($data) && array_key_exists($key, $data);
    }

    /**
     * Return true if a session var is not null and is not empty.
     * 
     * @param string $key Variable name
     * @return bool
     */
    public function valid(string $key): bool
    {
        return $this->has($key)
            && null !== ($_SESSION[$this->namespace][$key] ?? null);
    }

    /**
     * Return the count of session vars.
     * 
     * @return int
     */
    public function count(): int
    {
        $this->start();

        $data = $_SESSION[$this->namespace] ?? [];

        return is_array($data) ? count($data) : 0;
    }

    /**
     * Remove a session var by name.
     * 
     * @param string $key Variable name
     * @return void
     */
    public function remove(string $key): void
    {
        $this->start();

        unset($_SESSION[$this->namespace][$key]);
    }

    /**
     * Removes all session vars from current namespace.
     * 
     * @return void
     */
    public function clear(): void
    {
        $this->start();

        $_SESSION[$this->namespace] = [];
    }

    /**
     * Destroy the active session and session cookies if PHP sessions are configured to use cookies (session.use_cookies).
     * 
     * Check the server configuration (php.ini) to see if PHP sessions are configured to use cookies (session.use_cookies). 
     * If it's enabled (which is usually the case), delete it.
     * 
     * @return bool True on success or false on failure
     */
    public function destroy(): bool
    {
        if (!$this->started()) {
            return false;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Strict',
            ]);
        }

        return session_destroy();
    }

    /**
     * Saves the session data and ends the session. This is useful when you want to ensure 
     * that the session data is written to storage before the script ends, especially in 
     * long-running scripts or when you want to release the session lock.
     */
    public function save(): void
    {
        if ($this->started()) {
            session_write_close();
        }
    }

    /**
     * Set or overwrite a session var in object context.
     * 
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Retrieve a session var by name in object context.
     * 
     * @param string $key Variable name
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Return true if a session var is set in object context.
     * 
     * @param string $key Variable name
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Unset a session var by name in object context.
     * 
     * @param string $key Variable name
     * @return void
     */
    public function __unset(string $key): void
    {
        $this->remove($key);
    }

    /**
     * Assert that the session name is valid.
     * 
     * @param string $name The session name to validate
     * @return void
     * @throws InvalidArgumentException If the session name is invalid
     */
    private static function assertValidSessionName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new InvalidArgumentException(
                'Session name must contain only alphanumeric characters.'
            );
        }
    }

    /**
     * Check if the current request is using HTTPS.
     * 
     * @return bool
     */
    private static function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS'])
            && 'off' !== strtolower($_SERVER['HTTPS']);
    }
}
