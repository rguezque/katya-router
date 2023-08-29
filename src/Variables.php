<?php declare(strict_types = 1);

namespace rguezque;

class Variables {

    /**
     * Variables collection
     * 
     * @param Parameters
     */
    private $vars;

    public function __construct(array $variables = []) {
        $this->vars = new Parameters($variables);
    }

    /**
     * Set or overwrite a variable by name
     * 
     * @param string $name Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function setVar(string $name, $value): void {
        $name = strtolower($name);
        
        $this->vars->set($name, $value);
    }

    /**
     * Return a variable by name
     * 
     * @param string $name Variable name
     * @param mixed $default Optional default value to return
     * @return mixed
     */
    public function getVar(string $name, $default = null) {
        $name = strtolower($name);

        return $this->vars->get($name, $default);
    }

    /**
     * Return true if a variable exists, otherwise false
     * 
     * @param string $name variable name
     * @return bool
     */
    public function hasVar(string $name): bool {
        $name = strtolower($name);

        return $this->vars->has($name);
    }
}

?>