<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
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
 * This class provides a simple interface to register, unregister, and access services
 * in a collection. It allows you to define services using closures, which can be called
 * later to retrieve the service instance. The services are stored in an associative array
 * where the keys are the service names and the values are the closures that define the services.
 * 
 * @method Services register(string $alias, Closure $closure) Register services
 * @method Services unregister(string ...$alias) Unregister one or multiple services by name
 * @method Services only(array $names) Returns a Services instance filtered with only the defined services
 * @method bool has(string $key) Return true if a service exists
 * @method array all() Return all the services array
 * @method array names() Return the key names of availables services
 * @method int count() Return the count of services
 */
class Services {

    /**
     * Services collection
     * 
     * @var array<string, Closure>
     */
    private array $services = [];

    /**
     * Inicialize the Service Locator
     * 
     * @param array<string, Closure> $services_array The collection of services
     * @throws InvalidArgumentException When the services array does not comply with the expected structure
     */
    public function __construct(array $services_array = []) {
        foreach($services_array as $name => $service) {
            if(!is_string($name)) {
                throw new InvalidArgumentException('The collection of services must be an associative array, whose keys must be of type string and its values ​​of type Closure.');
            }
            if(!($service instanceof Closure)) {
                throw new InvalidArgumentException(sprintf('The service with name "%s" must be a Closure, catched %s', $name, gettype($service)));
            }
        }

        $this->services = $services_array;
    }

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
     * Return the names of availables services
     * 
     * @return array
     */
    public function names(): array {
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
     * Returns a Services instance filtered with only the defined services
     * 
     * @param string[] $names Service names to keep
     */
    public function only(array $names): Services {
        $filtered = clone $this;
        $to_remove = array_diff($this->names(), $names);
        if(!empty($to_remove)) {
            $filtered->unregister(...$to_remove);
        }

        return $filtered;
    }

    /**
     * Allow to access the private services. E.g. $services->db()
     * 
     * @param string $name Service name
     * @param array $params Service parameters
     * @return mixed
     * @throws NotFoundException
     */
    public function __call(string $name, array $params) {
        if(!$this->has($name)) {
            throw new NotFoundException(sprintf('The request service "%s" wasn\'t found.', $name));
        }

        return ($this->services[$name])(...$params);
    }

    /**
     * Allow to acces services in object context.  E.g. $services->db
     * 
     * @param string $name Service name
     * @return mixed
     * @throws NotFoundException
     */
    public function __get(string $name) {
        if(!$this->has($name)) {
            throw new NotFoundException(sprintf('The request service "%s" wasn\'t found.', $name));
        }

        return ($this->services[$name])();
    }
}
?>
