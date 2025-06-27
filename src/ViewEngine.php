<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use rguezque\Exceptions\FileNotFoundException;
use rguezque\Exceptions\NotFoundException;
use rguezque\Exceptions\PermissionException;

use function rguezque\functions\is_assoc_array;

/**
 * Simple engine that allows render templates
 * 
 * @method string fetch(tring $view, array $data = []) Fetch the template from buffer and return the result as string to be render after
 * @method void addArgument(string $key, mixed $value) Add an argument to be used in templates
 * @method void addArguments(array $data) Add arguments to be used in templates
 * @method void setArguments(array $data) Set the arguments to be used in templates
 */
class ViewEngine {
    /**
     * Templates directory
     * 
     * @var string
     */
    private $templates_dir;

    /**
     * Store arguments to be used in templates
     * 
     * @var array<string, mixed>
     */
    private array $arguments = [];

    /**
     * Initialize the template engine
     * 
     * @param string $templates_dir Templates directory
     * @throws NotFoundException When the templates directory does not exist
     * @throws PermissionException When the templates directory is not readable
     */
    public function __construct(string $templates_dir) {
        $templates_dir = rtrim($templates_dir, '/\\').'/'; // Ensure the directory ends with a separator
        if(!is_dir($templates_dir)) {
            throw new NotFoundException(sprintf('The templates directory "%s" does not exist', $templates_dir));
        }
        if(!is_readable($templates_dir)) {
            throw new PermissionException(sprintf('The templates directory "%s" is not readable', $templates_dir));
        }
        $this->templates_dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $templates_dir); // Normalize directory separators
    }

    /**
     * Fetch the template from buffer and return the result as string to be render after
     * 
     * @param string $view The template to render
     * @param array $data Arguments to send for template
     * @return string
     * @throws FileNotFoundException When the file template is not found
     * @throws InvalidArgumentException When the data are not an associative array
     */
    public function fetch(string $view, array $data = []): string {
        $view = trim($view, '/\\ ');
        if(!str_ends_with($view, '.php')) {
            $view .= '.php'; // Ensure the view has .php extension
        }
        $view = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $view); // Normalize directory separators
        $template_file = $this->templates_dir . $view;
        
        if(!file_exists($template_file)) {
            throw new FileNotFoundException(sprintf('The template "%s" is not found', $view));
        }

        if(!is_assoc_array($data)) {
            throw new InvalidArgumentException('The arguments must be an associative array');
        }

        $data = array_merge($this->arguments, $data); // Merge the arguments with the existing ones

        extract($data);

        ob_start();
        include $template_file;
        $rendered_view = ob_get_clean();

        return $rendered_view;
    }

    /**
     * Add an argument to be used in templates
     * 
     * @param string $key Argument key
     * @param mixed $value Argument value
     */
    public function addArgument(string $key, mixed $value): void {
        $this->arguments[trim($key)] = $value; // Add the argument to the
    }

    /**
     * Add arguments to be used in templates
     * 
     * @param array $data Arguments to add
     * @throws InvalidArgumentException When the arguments are not an associative array
     */
    public function addArguments(array $data): void {
        if(!is_assoc_array($data)) {
            throw new InvalidArgumentException('The arguments must be an associative array');
        }

        $this->arguments = array_merge($this->arguments, $data); // Merge the arguments with the existing ones
    }

    /**
     * Set the arguments to be used in templates
     * 
     * @param array $data Arguments to set
     * @throws InvalidArgumentException When the arguments are not an associative array
     */
    public function setArguments(array $data): void {
        if(!is_assoc_array($data)) {
            throw new InvalidArgumentException('The arguments must be an associative array');
        }

        $this->arguments = $data; // Set the arguments to the new ones
    }

}
