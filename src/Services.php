<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use Closure;
use InvalidArgumentException;
use rguezque\Exceptions\DuplicityException;
use rguezque\Exceptions\BadNameException;
use rguezque\Exceptions\NotFoundException;

/**
 * Services provider.
 * 
 * @method Services register(string $alias, Closure $closure) Register services
 * @method Services unregister(string ...$alias) Unregister one or multiple services by name
 * @method bool has(string $key) Return true if a service exists
 * @method array all() Return all the services array
 * @method array keys() Return key names of availables services
 * @method int count() Return the count of services
 */
class Services {

    /**
     * Services collection
     * 
     * @var array
     */
    private $services = [];

    /**
     * Register services
     * 
     * @param string $name Service name
     * @param Closure $closure Service definition
     * @return Services
     * @throws BadNameException
     * @throws InvalidArgumentException
     * @throws DuplicityException
     */
    public function register(string $name, Closure $closure): Services {
        if(strpos($name, ' ')) {
            throw new BadNameException(sprintf('Whitespaces not allowed in name definition for service "%s".', $name));
        }

        if(in_array($name, get_class_methods($this))) {
            throw new InvalidArgumentException(sprintf('"%s" is a reserved name for an existent property of %s and can\'t be overwrited.', $name, __CLASS__));
        }

        if(array_key_exists($name, $this->services)) {
            throw new DuplicityException(sprintf('Already exists a service with name "%s".', $name));
        }

        $this->services[$name] = $closure;

        return $this;
    }

    /**
     * Unregister one or multiple services by name
     * 
     * @param string ...$names Service names
     * @return Services
     */
    public function unregister(string ...$names): Services {
        foreach($names as $name) {
            unset($this->services[$name]);
        }

        return $this;
    }

    /**
     * Return true if a service exists
     * 
     * @param string $name Service name
     * @return bool
     */
    public function has(string $name): bool {
        return array_key_exists($name, $this->services);
    }

    /**
     * Return all services array
     * 
     * @return array
     */
    public function all(): array {
        return $this->services;
    }

    /**
     * Return key names of availables services
     * 
     * @return array
     */
    public function keys(): array {
        return array_keys($this->services);
    }

    /**
     * Return the count of services
     * 
     * @return int
     */
    public function count(): int {
        return count($this->services);
    }

    /**
     * Allow to access the private services
     * 
     * @param string $name Service name
     * @param array $params Service parameters
     * @return mixed
     * @throws NotFoundException
     */
    public function __call(string $name, array $params) {
        if(!isset($this->services[$name]) && !is_callable($this->services[$name])) {
            throw new NotFoundException(sprintf('The request service "%s" wasn\'t found.', $name));
        }

        return call_user_func($this->services[$name], ...$params);
    }

    /**
     * Allow to acces services in object context
     * 
     * @param string $name Service name
     * @return mixed
     * @throws NotFoundException
     */
    public function __get(string $name) {
        if(!isset($this->services[$name])) {
            throw new NotFoundException(sprintf('The request service "%s" wasn\'t found.', $name));
        }

        $service = $this->services[$name];

        return $service();
    }
}
?>
