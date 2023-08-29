<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use JsonSerializable;
use rguezque\Interfaces\CollectionInterface;

/**
 * Contain a parameters array.
 * 
 * @method mixed get(string $key, $default = null) Retrieve a parameter by name
 * @method array all() Retrieve all parameters array
 * @method bool has(string $key) Return true if a parameter exists
 * @method bool valid(string $key) Return true if a parameter exists and is not empty or null
 * @method int count() Return the count of parameters
 * @method string gettype(string $key) Return the type of a parameter
 * @method array keys() Retrieve all the parameters array keys
 */
class Parameters implements CollectionInterface, JsonSerializable {

    /**
     * Parameters array
     * 
     * @var array
     */
    protected $bunch;

    /**
     * Receive a parameters array
     * 
     * @param array $bunch Parameters definition
     */
    public function __construct(array $bunch) {
        $this->bunch = $bunch;
    }

    /**
     * Return a parameter by name
     * 
     * If the parameter is array, return into a Parameters object
     * 
     * @param string $key Parameter name
     * @param mixed $default Value to return if the parameter isn't found
     * @return mixed|Parameters
     */
    public function get(string $key, $default = null) {
        $key = trim($key);

        return $this->has($key) 
        ? (is_array($this->bunch[$key]) ? new Parameters($this->bunch[$key]) : $this->bunch[$key]) 
        : $default ;
    }

    /**
     * Set or overwrite a parameter by name
     * 
     * @param string $key Parameter name
     * @param mixed $value Parameter value
     * @param void
     */
    public function set(string $key, $value): void {
        $key = trim($key);
        $this->bunch[$key] = $value;
    }

    /**
     * Retrieve all parameters array
     * 
     * {@inheritdoc}
     */
    public function all(): array {
        return $this->bunch;
    }

    /**
     * Return true if a parameter exists
     * 
     * @param string $key Parameter name
     * @return bool
     */
    public function has(string $key): bool {
        $key = trim($key);
        
        return array_key_exists($key, $this->bunch);
    }

    /**
     * Return true if a parameter exists and is not empty or null
     * 
     * @param string $key Parameter name
     * @return bool
     */
    public function valid(string $key): bool {
        return $this->has($key) && !empty($this->bunch[$key]) && !is_null($this->bunch[$key]);
    }

    /**
	 * Return the count of parameters
	 * 
	 * {@inheritdoc}
	 */
    public function count(): int {
    	return sizeof($this->bunch);
    }

    /**
     * Return the type of a parameter
     * 
     * @param string $key Parameter name
     * @return string
     */
    public function gettype(string $key): string {
        return gettype($this->get($key));
    }

    /**
     * Retrieve all the parameters array keys
     * 
     * {@inheritdoc}
     */
    public function keys(): array {
        return array_keys($this->bunch);
    }

    /**
     * Print all parameters in readable format if the class is invoked like a string
     * 
     * @return string
     */
    public function __toString(): string {
        return sprintf('<pre>%s</pre>', print_r($this->bunch, true));
    }

    /**
     * Specify data which should be serialized to JSON. Serializes the object to a value 
     * that can be serialized natively by json_encode().
     * 
     * @return array Returns data which can be serialized by json_encode(), which is a value 
     *               of any type other than a resource.
     */
    public function jsonSerialize() {
        return $this->bunch;
    }

}

?>