<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use rguezque\Interfaces\ArgumentsInterface;
use rguezque\Interfaces\BagInterface;

use function rguezque\functions\env;

/**
 * Represents a PHP session.
 * 
 * This class provides methods to create, start, and manage session variables.
 * It allows you to set, get, remove, and check the existence of session variables.
 * The session variables are stored in a specific namespace, which can be customized.
 * The class implements the BagInterface and ArgumentsInterface, providing a consistent
 * interface for managing session variables.
 * 
 * @method Session withNamespace(?string $session_name = null) Create or select the specified namespace of the session variables and return an instance of `Session` with the Singleton pattern
 * @method string getNamespace() Return the current session vars namespace
 * @method bool alreadyExists(string $namespace) Return `true` if a namespace already exists, otherwise `false`
 * @method void start() Starts or resume a session
 * @method void regenerateId(bool $delete_old_session = true) Regenerates the session ID to prevent session fixation attacks.
 * @method bool started() Return true if already exists an active session, otherwise false
 * @method void set(string $key, mixed $value) Set or overwrite a session var
 * @method void get(string $key, mixed $default = null) If exists, retrieve a session var by name, otherwise returns default
 * @method array all() Retrieve all session vars in the current namespace
 * @method bool has(string $key) Return true if exists a session var by name
 * @method bool valid(string $key) Return true if a session var is not null and is not empty
 * @method int count() Return the count of session vars
 * @method void remove(string $key) Removes a session var by name
 * @method void clear() Removes all session vars
 * @method bool destroy() Destroy the active session
 */
class Session implements BagInterface, ArgumentsInterface {
    /**
     * Custom session vars namespace
     * 
     * @var string
     */
    private string $namespace = '';

    /**
     * Store instances of `Session`
     * 
     * @var array
     */
    private static array $instances = [];

    /**
     * Initialize a session
     * 
     * @param string $namespace Custom session vars namespace
     */
    protected function __construct(string $namespace) {
        $this->namespace = $namespace;
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
    public static function withNamespace(string $namespace): Session {
        $session_namespace = trim($namespace);
        
        if($session_namespace === '') {
            throw new InvalidArgumentException('The namespace is not valid. Empty string is NOT allowed.');
        }

        if (!self::exists($session_namespace)) {
            self::$instances[$session_namespace] = new self($session_namespace);
        }

        return self::$instances[$session_namespace];
    }

    /**
     * Return `true` if a namespace already exists, otherwise `false`
     * 
     * @param string $namespace The session vars namespace to check if it already exists
     * @return bool
     */
    public static function exists(string $namespace): bool {
        return isset(self::$instances[trim($namespace)]);
    }

    /**
     * Return the current session vars namespace
     * 
     * @return string
     */
    public function getNamespace(): string {
        return $this->namespace;
    }

    /**
     * Starts or resume a session
     * 
     * @return void
     */
    public function start(): void {
        if(!$this->started()) {
            session_name($this->namespace);
            session_start();
        }
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     * Should be called ONLY on authentication events (login/logout).
     * 
     * @param bool $delete_old_session Flag for indicate regenerate session id
     * @return void
     */
    public function regenerateId(bool $delete_old_session = true): void {
        if ($this->started()) {
            session_regenerate_id($delete_old_session);
        }
    }

    /**
     * Return true if already exists an active session, otherwise false
     * 
     * @return bool
     */
    public function started(): bool {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Set or overwrite a session var
     * 
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function set(string $key, mixed $value): void {
        $this->start();
        $_SESSION[$this->namespace][$key] = $value;
    }

    /**
     * Set or overwrite a session var in object context
     * 
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function __set(string $key, mixed $value): void {
        $this->set($key, $value);
    }

    /**
     * If exists, retrieve a session var by name, otherwise returns default
     * 
     * @param string $key Variable name
     * @param mixed $default Default value to return
     * @return mixed
     */
    public function get(string $key, mixed $default = null) {
        $this->start();
        return $this->has($key) ? $_SESSION[$this->namespace][$key] : $default;
    }

    /**
     * Retrieve a session var by name in object context
     * 
     * @param string $key Variable name
     * @return mixed
     */
    public function __get(string $key) {
        return $this->get($key);
    }

    /**
     * Retrieve all session vars in the current namespace
     * 
     * @return array
     */
    public function all(): array {
        $this->start();
        return (array) $_SESSION[$this->namespace];
    }

    /**
     * Return true if exists a session var by name
     * 
     * @param string $key Variable name
     * @return bool
     */
    public function has(string $key): bool {
        $this->start();
        return isset($_SESSION[$this->namespace]) && array_key_exists($key, $_SESSION[$this->namespace]);
    }

    /**
     * Return true if a session var is not null and is not empty
     * 
     * @param string $key Variable name
     * @return bool
     */
    public function valid(string $key): bool {
        $this->start();
        return $this->has($key) && !empty($_SESSION[$this->namespace][$key]) && !is_null($_SESSION[$this->namespace][$key]);
    }

    /**
     * Return the count of session vars
     * 
     * @return int
     */
    public function count(): int {
        $this->start();
    	return isset($_SESSION[$this->namespace]) ? count($_SESSION[$this->namespace]) : 0;
    }

    /**
     * Remove a session var by name
     * 
     * @param string $key Variable name
     * @return void
     */
    public function remove(string $key): void {
        $this->start();
        unset($_SESSION[$this->namespace][$key]);
    }

    /**
     * Removes all session vars from current namespace
     * 
     * @return void
     */
    public function clear(): void {
        unset($_SESSION[$this->namespace]);
    }

    /**
     * Destroy the active session
     * 
     * This method will clear all session variables,
     * remove the session cookie if it exists, and destroy the session.
     * 
     * @return bool True on success or false on failure
     */
    public function destroy(): bool {
        $this->start();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        $this->clear();
        return session_destroy();
    }

}