<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use rguezque\Interfaces\ArgumentsInterface;
use rguezque\Interfaces\BagInterface;

/**
 * Represents a PHP session.
 * 
 * This class provides methods to create, start, and manage session variables.
 * It allows you to set, get, remove, and check the existence of session variables.
 * The session variables are stored in a specific namespace, which can be customized.
 * The class implements the BagInterface and ArgumentsInterface, providing a consistent
 * interface for managing session variables.
 * 
 * @method Session create(string $session_name = Session::NAMESPACE) Create or select a collection of session vars into the default router-session-vars-namespace
 * @method void start() Starts or resume a session
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
     * Default session vars namespace
     * 
     * @var string
     */
    private const NAMESPACE = '__KATYA_ROUTER_SESSION_VARS__';

    /**
     * Custom session vars namespace
     * 
     * @var string
     */
    private string $namespace = '';

    /**
     * Store instance of Session
     * 
     * @var Session
     */
    private static ?Session $instance = null;

    /**
     * Initialize a session
     * 
     * @param string $namespace Custom session vars namespace
     */
    protected function __construct(string $namespace = Session::NAMESPACE) {
        $this->namespace = $namespace;
    }

    /**
     * Create or select a collection of session vars into the default router-session-vars-namespace
     * 
     * @param string $session_name Name for the current session (Must be only alphanumeric)
     * @return Session
     */
    public static function create(string $session_name = Session::NAMESPACE): Session {
        if(!self::$instance || self::$instance->namespace !== $session_name) {
            self::$instance = new Session($session_name);
        }

        return self::$instance;
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
        return isset($this->namespace, $_SESSION) && array_key_exists($key, $_SESSION[$this->namespace]);
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
     * Removes all session vars
     * 
     * @return void
     */
    public function clear(): void {
        if (isset($_SESSION[$this->namespace])) {
            unset($_SESSION[$this->namespace]);
        }
    }

    /**
     * Destroy the active session
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

    /**
     * Print all session vars in readable format if the class is invoked like a string
     * 
     * @return string
     */
    public function __toString(): string {
        $this->start();
        return sprintf('<pre>%s</pre>', print_r($_SESSION[$this->namespace], true));
    }

}
?>