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
 * @method Variables setVar(string $name, $value) Set or overwrite a variable by name
 * @method mixed getVar(string $name, $default = null) Return a variable by name is exists, otherwise return default specified value
 * @method bool hasVar(string $name) Return true if a variable exists, otherwise false
 */
class Variables {

    /**
     * Variables collection
     * 
     * @var Parameters
     */
    private Parameters $vars;

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
    public function setVar(string $name, $value): Variables {
        $name = trim($name);
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
    public function getVar(string $name, $default = null) {
        $name = trim($name);

        return $this->vars->get($name, $default);
    }

    /**
     * Return true if a variable exists, otherwise false
     * 
     * @param string $name variable name
     * @return bool
     */
    public function hasVar(string $name): bool {
        $name = trim($name);

        return $this->vars->has($name);
    }
}

?>