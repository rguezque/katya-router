<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\Interfaces;

/**
 * Contain a parameters array.
 * 
 * This interface defines methods for managing a collection of parameters.
 * It allows you to set or overwrite a parameter, remove a parameter by name,
 * and clear all parameters from the collection.
 * 
 * @method void set(string $key, $value) Set or overwrite a parameter
 * @method void remove(string $key) Remove a parameter by name
 * @method void clear() Remove all parameters
 */
interface ArgumentsInterface {
    /**
     * Set or overwrite a parameter
     * 
     * @param string $key Parameter name
     * @param mixed $value Parameter value
     * @return void
     */
    public function set(string $key, $value): void;

    /**
     * Remove a parameter by name
     * 
     * @param string $key Parameter name
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Remove all parameters
     * 
     * @return void
     */
    public function clear(): void;
}

?>