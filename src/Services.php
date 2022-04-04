<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022 Luis Arturo Rodríguez <rguezque@gmail.com>
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
 * @method array names() Return names of availables services
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
     * @param string $alias Service name
     * @param Closure $closure Service registered
     * @return Services
     * @throws BadNameException
     * @throws InvalidArgumentException
     * @throws DuplicityException
     */
    public function register(string $alias, Closure $closure): Services {
        if(strpos($alias, ' ')) {
            throw new BadNameException(sprintf('Whitespaces not allowed in name definition for service "%s".', $alias));
        }

        if(in_array($alias, get_class_methods($this))) {
            throw new InvalidArgumentException(sprintf('"%s" is a reserved name for an existent property of %s and can\'t be overwrited.', $alias, __CLASS__));
        }

        if(array_key_exists($alias, $this->services)) {
            throw new DuplicityException(sprintf('Already exists a service with name "%s".', $alias));
        }

        $this->services[$alias] = $closure;

        return $this;
    }

    /**
     * Unregister one or multiple services by name
     * 
     * @param string ...$alias Service names
     * @return Services
     */
    public function unregister(string ...$alias): Services {
        foreach($alias as $name) {
            unset($this->services[$name]);
        }

        return $this;
    }

    /**
     * Return true if a service exists
     * 
     * @param string $key Service name
     * @return bool
     */
    public function has(string $key): bool {
        return array_key_exists($key, $this->services);
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
     * Allow to access the private services
     * 
     * @param string $method Method name
     * @param array $params Method parameters
     * @return mixed
     * @throws NotFoundException
     */
    public function __call(string $method, array $params) {
        if(!isset($this->services[$method]) && !is_callable($this->services[$method])) {
            throw new NotFoundException(sprintf('The request service "%s" wasn\'t found.', $method));
        }

        return call_user_func($this->services[$method], ...$params);
    }

    /**
     * Allow to acces services in object context
     * 
     * @param string $method Sevice name
     * @return mixed
     * @throws NotFoundException
     */
    public function __get(string $method) {
        if(!isset($this->services[$method])) {
            throw new NotFoundException(sprintf('The request service "%s" wasn\'t found.', $method));
        }

        $service = $this->services[$method];

        return $service();
    }
}
?>
