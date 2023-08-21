<?php declare(strict_types = 1);

namespace rguezque\Interfaces;

/**
 * Interface for variable collection.
 * 
 * @method mixed get(string $key, $default = null) Retrieve a variable by name
 * @method array all() Retrieve all variables array
 * @method bool has(string $key) Return true if a variable exists
 * @method bool valid(string $key) Return true if a variable exists and is not empty or null
 * @method int count() Return the count of variables
 * @method string gettype(string $key) Return the type of a variable
 * @method array keys() Retrieve all the variables array keys
 */
interface Collection {

    /**
     * Retrieve a variable by name
     * 
     * @param string $key Variable name
     * @param mixed $default Value to return if the variable isn't found
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Retrieve all variables array
     * 
     * @return array
     */
    public function all(): array;

    /**
     * Return true if a variable exists
     * 
     * @param string $key Variable name
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Return true if a variable exists and is not empty or null
     * 
     * @param string $key variable name
     * @return bool
     */
    public function valid(string $key): bool;

    /**
	 * Return the count of variables
	 * 
	 * @return int
	 */
    public function count(): int;

    /**
     * Return the type of a variable
     * 
     * @param string $key Variable name
     * @return string
     */
    public function gettype(string $key): string;

    /**
     * Retrieve all the variables array keys
     * 
     * @return string[]
     */
    public function keys(): array;

}

?>