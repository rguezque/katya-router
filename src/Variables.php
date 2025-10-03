<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represents variables setted and used across application
 * 
 * This class provides a simple interface to manage variables
 * in a collection. It allows you to set, get, and check the existence
 * of variables by name. The variables are stored in a Parameters collection.
 * 
 * @method Variables set(string $name, $value) Set or overwrite a variable by name
 * @method mixed get(string $name, $default = null) Return a variable by name is exists, otherwise return default specified value
 * @method bool has(string $name) Return true if a variable exists, otherwise false
 */
class Variables {

    /**
     * Variables collection
     * 
     * @var Parameters
     */
    private Parameters $vars;

    /**
     * Initialize the vars collection
     * 
     * @param array<string, mixed> $variables The variables array
     */
    public function __construct(array $variables = []) {
        $this->vars = new Parameters($variables);
    }

    /**
     * Set or overwrite a variable by name
     * 
     * @param string $name Variable name
     * @param mixed $value Variable value
     * @return Variables
     */
    public function set(string $name, $value): Variables {
        $this->vars->set($name, $value);
        return $this;
    }

    /**
     * Return a variable by name is exists, otherwise return default specified value
     * 
     * @param string $name Variable name
     * @param mixed $default Optional default value to return
     * @return mixed
     */
    public function get(string $name, $default = null) {
        return $this->vars->get($name, $default);
    }

    /**
     * Return true if a variable exists, otherwise false
     * 
     * @param string $name variable name
     * @return bool
     */
    public function has(string $name): bool {
        return $this->vars->has($name);
    }
}

?>