<?php

declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use rguezque\Exception\FileNotFoundException;
use rguezque\Exception\NotFoundException;
use rguezque\Exception\PermissionException;
use SplFileInfo;
use function rguezque\functions\is_assoc_array;

/**
 * Simple engine that allows render templates
 *
 * @method string fetch(string $view, array $data = []) Fetch the template from buffer and return the result as string to be render after
 * @method ViewEngine fetchFragment(string $template, string $name, array $data = []) Add a template fragment fetched as argument, to extend a main view
 * @method ViewEngine addDirectory(string $namespace, string $dir_path) Register a directory to search for templates, under a namespace
 * @method ViewEngine addArgument(string $key, mixed $value) Add an argument to be used in templates
 * @method ViewEngine addArguments(array $data) Add arguments to be used in templates
 * @method ViewEngine setArguments(array $data) Set the arguments to be used in templates
 * @method void insert(string $partial, array $data = []) Allow insert a template fragment directly into a view template
 * @method string e(?string $string) Escape strings for secure HTML output
 * @method string asset(string $path) Generates an absolute or relative URL for a resource (CSS, JS, images) by adding a timestamp to break the browser cache (Cache Busting).
 */
class ViewEngine
{
    /**
     * Templates directory
     *
     * @var string
     */
    private string $templates_dir;

    /**
     * Mapped namespaces for extra template directories
     *
     * @var array<string, string>
     */
    private array $namespaces = [];

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
     * @param array<string, string> $namespaces Associative array of namespace => directory paths
     * @throws NotFoundException When the templates directory does not exist or cache cannot be created
     * @throws PermissionException When directories are not readable/writable
     */
    public function __construct(string $templates_dir, array $namespaces = [])
    {
        $templates_dir = $this->validateDirectory($templates_dir);
        $this->templates_dir = $templates_dir;

        // Validate and store extra namespaces
        foreach ($namespaces as $namespace => $dir) {
            $dir = $this->validateDirectory($namespace, $dir);
            $this->namespaces[trim($namespace)] = $dir;
        }
    }

    /**
     * Register a directory to search for templates, under a namespace
     * 
     * @param string $namespace Dicrectory namespace
     * @param string $dir_path The full path to the templates directory
     * @return ViewEngine
     */
    public function addDirectory(string $namespace, string $dir_path): ViewEngine
    {
        $dir_path = $this->validateDirectory($dir_path, $namespace);
        $this->namespaces[$namespace] = $dir_path;
        return $this;
    }

    /**
     * Fetch the template from buffer and return the result as string to be render after
     *
     * @param string $view The template to render (supports "namespace::view" syntax)
     * @param array $data Arguments to send for template
     * @return string
     * @throws FileNotFoundException When the file template is not found
     * @throws InvalidArgumentException When the namespace is not registered
     */
    public function fetch(string $view, array $data = []): string
    {
        $base_dir = $this->templates_dir;

        // Check for namespace syntax (e.g., "admin::dashboard")
        if (str_contains($view, '::')) {
            [$namespace, $view] = explode('::', $view, 2);

            if (!isset($this->namespaces[$namespace])) {
                throw new InvalidArgumentException(sprintf('The namespace "%s" is not registered in the ViewEngine', $namespace));
            }

            $base_dir = $this->namespaces[$namespace];
        }

        $view = trim($view, '/\\ ');
        if (!str_ends_with($view, '.view.php')) {
            $view .= '.view.php';
        }
        $view = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $view);

        $template_file = $base_dir . $view;

        if (!file_exists($template_file)) {
            throw new FileNotFoundException(sprintf('The template "%s" was not found', $view));
        }

        $data = array_merge($this->arguments, $data);
        extract($data);

        ob_start();
        include $template_file;
        return ob_get_clean();
    }

    /**
     * Add a template fragment fetched as argument, to extend a main view
     *
     * @param string $template Template name to fetch
     * @param string $name variable name for the template fetched
     * @param array $data Arguments to send for template fetched
     * @return ViewEngine
     */
    public function fetchFragment(string $template, string $name, array $data = []): ViewEngine
    {
        $fetched = $this->fetch($template, $data);
        $this->addArgument($name, $fetched);
        return $this;
    }

    /**
     * Add an argument to be used in templates
     *
     * @param string $key Argument key
     * @param mixed $value Argument value
     * @return ViewEngine
     */
    public function addArgument(string $key, mixed $value): ViewEngine
    {
        $this->arguments[trim($key)] = $value; // Add the argument to the
        return $this;
    }

    /**
     * Add arguments to be used in templates
     *
     * @param array $data Arguments to add
     * @return ViewEngine
     * @throws InvalidArgumentException When the arguments are not an associative array
     */
    public function addArguments(array $data): ViewEngine
    {
        if (!is_assoc_array($data)) {
            throw new InvalidArgumentException('The arguments must be an associative array');
        }
        $this->arguments = array_merge($this->arguments, $data); // Merge the arguments with the existing ones
        return $this;
    }

    /**
     * Set the arguments to be used in templates, overwriting any existing arguments
     *
     * @param array $data Arguments to set
     * @return ViewEngine
     * @throws InvalidArgumentException When the arguments are not an associative array
     */
    public function setArguments(array $data): ViewEngine
    {
        if (!is_assoc_array($data)) {
            throw new InvalidArgumentException('The arguments must be an associative array');
        }
        $this->arguments = $data; // Set the arguments to the new ones
        return $this;
    }

    /**
     * Allow insert a template fragment directly into a view template
     *
     * @param string $partial The template fragment to render
     * @param array $data Arguments to send for partial
     * @return void
     */
    public function insert(string $partial, array $data = []): void
    {
        $partial = $this->fetch($partial, $data);
        echo $partial;
    }

    /**
     * Escape a string for safe output in HTML
     *
     * @param string|null $string Text to escape
     * @return string Secured formatted text for HTML
     */
    public function e(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generates an absolute or relative URL for a resource (CSS, JS, images)
     * by adding a timestamp to break the browser cache (Cache Busting).
     *
     * @param string $path Asset path
     * @return string
     * @throws FileNotFoundException When the asset do not exists
     */
    public function asset(string $path): string // Nota: Se agregó 'public' que faltaba en el original
    {
        $path = '/' . ltrim($path, '/\\');
        $asset_file = $_SERVER['DOCUMENT_ROOT'] . $path;

        if (!file_exists($asset_file)) {
            throw new FileNotFoundException(sprintf('The asset "%s" was not found', $asset_file));
        }

        // Add the version parameter (keeps previous parameters if they existed)
        $separator = str_contains($path, '?') ? '&' : '?';
        $version = filemtime($asset_file);
        $cached_path = $asset_file . $separator . 'v=' . $version;

        return $cached_path;
    }

    /**
     * Validates that a directory exists and has read permissions
     * 
     * @param string $dir_path Directory to validate
     * @param string $namespace Directory namespace (optional)
     * @return string The normalized directory path
     */
    private function validateDirectory(string $dir_path, ?string $namespace = null): string
    {
        $dir = rtrim((string)$dir_path, '/\\') . DIRECTORY_SEPARATOR;
        $spl_ns_info = new SplFileInfo($dir);

        if (!$spl_ns_info->isDir()) {
            (!is_null($namespace))
                ? throw new NotFoundException(sprintf('The namespace directory "%s" for "%s" does not exist', $dir, $namespace))
                : throw new NotFoundException(sprintf('The templates directory "%s" does not exist', $dir));;
        }
        if (!$spl_ns_info->isReadable()) {
            (!is_null($namespace))
                ? throw new PermissionException(sprintf('The namespace directory "%s" for "%s" is not readable', $dir, $namespace))
                : throw new PermissionException(sprintf('The templates directory "%s" is not readable', $dir));;
        }

        return $dir;
    }
}
