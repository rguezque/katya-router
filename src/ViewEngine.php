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

/**
 * Simple engine that allows render templates
 *
 * @method string fetch(string $view, array $data = []) Fetch the template from buffer and return the result as string to be render after
 * @method void startSection(string $name) Start capturing a named section to be yielded later in the layout
 * @method void stopSection() Stop the currently active section capture and save its content
 * @method void yieldSection(string $name, string $default = '') Output a section content or a default value if it has not been defined
 * @method void extends(string $template) Set the parent template that should wrap the current view
 * @method ViewEngine fetchFragment(string $template, string $name, array $data = []) Add a template fragment fetched as argument, to extend a main view
 * @method ViewEngine addDirectory(string $namespace, string $dir_path) Register a directory to search for templates, under a namespace
 * @method ViewEngine addArgument(string $key, mixed $value) Add an argument to be used in templates
 * @method ViewEngine addArguments(array $data) Add arguments to be used in templates
 * @method ViewEngine setArguments(array $data) Set the arguments to be used in templates
 * @method void insert(string $partial, array $data = []) Allow insert a template fragment directly into a view template
 * @method string e(?string $string) Escape strings for secure HTML output
 * @method string asset(string $path) Generates a root-relative URL for a resource (CSS, JS, images) by adding a timestamp to break the browser cache (Cache Busting).
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
     * Store shared arguments to be used in templates
     *
     * @var array<string, mixed>
     */
    private array $shared_arguments = [];

    /**
     * Collected section contents for the current render
     *
     * @var array<string, string>
     */
    private array $sections = [];

    /**
     * Active section output buffers
     *
     * @var list<array{name: string, buffer_level: int}>
     */
    private array $section_stack = [];

    /**
     * Layout selected by the currently rendered view
     */
    private ?string $layout = null;

    /**
     * Current template include depth
     */
    private int $render_depth = 0;

    /**
     * In-memory cache for asset file modification times (Cache Busting performance optimization)
     *
     * @var array<string, int>
     */
    private array $asset_cache = [];

    /**
     * In-memory cache for real base directories (Performance optimization)
     *
     * @var array<string, string>
     */
    private array $real_base_dirs = [];

    /**
     * In-memory cache for resolved template file paths (Performance optimization)
     *
     * @var array<string, string>
     */
    private array $template_cache = [];

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
        $this->real_base_dirs[$templates_dir] = realpath($templates_dir);

        // Validate and store extra namespaces
        foreach ($namespaces as $namespace => $dir) {
            $dir = $this->validateDirectory($dir, $namespace);
            $this->namespaces[trim($namespace)] = $dir;
            $this->real_base_dirs[$dir] = realpath($dir);
        }
    }

    /**
     * Register a directory to search for templates, under a namespace
     * 
     * @param string $namespace Directory namespace
     * @param string $dir_path The full path to the templates directory
     * @return ViewEngine
     */
    public function addDirectory(string $namespace, string $dir_path): ViewEngine
    {
        $dir_path = $this->validateDirectory($dir_path, $namespace);
        $this->namespaces[$namespace] = $dir_path;
        $this->real_base_dirs[$dir_path] = realpath($dir_path);
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
        $is_root_render = $this->render_depth === 0;
        $previous_state = null;

        if ($is_root_render) {
            $this->sections = [];
            $this->section_stack = [];
            $this->layout = null;
        } else {
            $previous_state = [$this->sections, $this->section_stack, $this->layout];
            $this->sections = [];
            $this->section_stack = [];
            $this->layout = null;
        }

        try {
            $output = $this->renderTemplate($view, $data);
            if (!$is_root_render) {
                return $output;
            }

            $rendered_layouts = [];
            while ($this->layout !== null) {
                $layout = $this->layout;
                if (in_array($layout, $rendered_layouts, true)) {
                    throw new InvalidArgumentException(sprintf('A circular layout dependency was detected for "%s"', $layout));
                }

                $rendered_layouts[] = $layout;
                $this->layout = null;
                $output = $this->renderTemplate($layout, $data);
            }

            return $output;
        } finally {
            if ($is_root_render) {
                $this->sections = [];
                $this->section_stack = [];
                $this->layout = null;
            } else {
                [$this->sections, $this->section_stack, $this->layout] = $previous_state;
            }
        }
    }

    /**
     * Start capturing output for a section
     *
     * @param string $name Section name
     * @return void
     * @throws InvalidArgumentException When the section name is empty
     */
    public function startSection(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('The section name cannot be empty');
        }

        $this->section_stack[] = ['name' => $name, 'buffer_level' => ob_get_level()];
        ob_start();
    }

    /**
     * Stop capturing and store the current section
     *
     * @return void
     * @throws InvalidArgumentException When no section is active or its buffer is unbalanced
     */
    public function stopSection(): void
    {
        $section = end($this->section_stack);
        if ($section === false) {
            throw new InvalidArgumentException('There is no active section to stop');
        }
        if (ob_get_level() !== $section['buffer_level'] + 1) {
            throw new InvalidArgumentException('The section output buffers are unbalanced');
        }

        array_pop($this->section_stack);
        $content = ob_get_clean();
        if ($content === false) {
            throw new InvalidArgumentException('The section output buffer could not be read');
        }

        $this->sections[$section['name']] = $content;
    }

    /**
     * Print a collected section or its default content
     *
     * @param string $name Section name
     * @param string $default Content to print when the section is undefined
     * @return void
     */
    public function yieldSection(string $name, string $default = ''): void
    {
        echo $this->sections[trim($name)] ?? $default;
    }

    /**
     * Select the layout to render around the current view
     *
     * @param string $template Layout template name
     * @return void
     * @throws InvalidArgumentException When the template name is empty
     */
    public function extends(string $template): void
    {
        $template = trim($template);
        if ($template === '') {
            throw new InvalidArgumentException('The layout template name cannot be empty');
        }

        $this->layout = $template;
    }

    /**
     * Render a template file without resolving its selected layout
     *
     * @param string $view Template name
     * @param array $data Arguments to send to the template
     * @return string
     * @throws FileNotFoundException When the file template is not found
     * @throws InvalidArgumentException When the namespace is not registered
     */
    private function renderTemplate(string $view, array $data): string
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

        $cache_key = $base_dir . '|' . $view;

        if (isset($this->template_cache[$cache_key])) {
            $template_file = $this->template_cache[$cache_key];
            $real_base_dir = $this->real_base_dirs[$base_dir];
        } else {
            $real_base_dir = $this->real_base_dirs[$base_dir] ?? realpath($base_dir);
            $template_file = realpath($base_dir . $view);

            if ($real_base_dir === false || $template_file === false || !str_starts_with($template_file, $real_base_dir)) {
                throw new FileNotFoundException(sprintf('The template "%s" was not found', $view));
            }

            $this->template_cache[$cache_key] = $template_file;
        }

        $data = array_merge($this->shared_arguments, $data);
        extract($data, EXTR_SKIP);

        $buffer_level = ob_get_level();
        ob_start();
        $this->render_depth++;
        try {
            include $template_file;
            $output = ob_get_contents();
            if ($output === false) {
                throw new InvalidArgumentException('The template output buffer could not be read');
            }

            return $output;
        } finally {
            while (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
            $this->render_depth--;
        }
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
     * Add a shared argument to be used in templates
     *
     * @param string $key Argument key
     * @param mixed $value Argument value
     * @return ViewEngine
     */
    public function addArgument(string $key, mixed $value): ViewEngine
    {
        $this->shared_arguments[trim($key)] = $value; // Add the argument to the shared arguments
        return $this;
    }

    /**
     * Add shared arguments to be used in templates
     *
     * @param array $data Arguments to add
     * @return ViewEngine
     * @throws InvalidArgumentException When the arguments are not an associative array
     */
    public function addArguments(array $data): ViewEngine
    {
        if (array_is_list($data)) {
            throw new InvalidArgumentException('The arguments must be an associative array');
        }
        $this->shared_arguments = array_merge($this->shared_arguments, $data); // Merge the arguments with the existing ones
        return $this;
    }

    /**
     * Set the shared arguments to be used in templates, overwriting any existing arguments
     *
     * @param array $data Shared arguments to set
     * @return ViewEngine
     * @throws InvalidArgumentException When the arguments are not an associative array
     */
    public function setArguments(array $data): ViewEngine
    {
        if (array_is_list($data)) {
            throw new InvalidArgumentException('The arguments must be an associative array');
        }
        $this->shared_arguments = $data; // Set the arguments to the new ones
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
     * Generates a root-relative URL for a resource (CSS, JS, images)
     * by adding a timestamp to break the browser cache (Cache Busting).
     *
     * @param string $path Asset path
     * @return string
     * @throws FileNotFoundException When the asset do not exists
     */
    public function asset(string $path): string
    {
        $url_parts = parse_url(str_replace('\\', '/', $path));
        if ($url_parts === false) {
            throw new FileNotFoundException(sprintf('The asset "%s" was not found', $path));
        }

        $url_path = $url_parts['path'] ?? '';
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';

        if ($url_path === '' || $document_root === '') {
            throw new FileNotFoundException(sprintf('The asset "%s" was not found', $path));
        }

        $asset_file = rtrim($document_root, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode(ltrim($url_path, '/')));

        if (!is_file($asset_file)) {
            throw new FileNotFoundException(sprintf('The asset "%s" was not found', $asset_file));
        }

        if (isset($this->asset_cache[$asset_file])) {
            $version = $this->asset_cache[$asset_file];
        } else {
            $version = filemtime($asset_file);
            if ($version === false) {
                throw new FileNotFoundException(sprintf('The asset "%s" was not found', $asset_file));
            }
            $this->asset_cache[$asset_file] = $version;
        }

        $asset_url = '/' . ltrim($url_path, '/');
        $query = $url_parts['query'] ?? '';
        $fragment = isset($url_parts['fragment']) ? '#' . $url_parts['fragment'] : '';

        return $asset_url . '?' . ($query !== '' ? $query . '&' : '') . 'v=' . $version . $fragment;
    }

    /**
     * Validates that a directory exists and has read permissions
     * 
     * @param string $dir_path Directory to validate
     * @param string $namespace Directory namespace (optional)
     * @return string The normalized directory path
     * @throws NotFoundException If the directory does not exist
     * @throws PermissionException If the directory is not readable
     */
    private function validateDirectory(string $dir_path, ?string $namespace = null): string
    {
        $dir = rtrim((string)$dir_path, '/\\') . DIRECTORY_SEPARATOR;
        $spl_ns_info = new SplFileInfo($dir);

        if (!$spl_ns_info->isDir()) {
            $message_not_dir = !is_null($namespace)
                ? sprintf('The namespace directory "%s" for "%s" does not exist', $dir, $namespace)
                : sprintf('The templates directory "%s" does not exist', $dir);
            throw new NotFoundException($message_not_dir);
        }

        if (!$spl_ns_info->isReadable()) {
            $message_not_readable = !is_null($namespace)
                ? sprintf('The directory "%s" for namespace "%s" is not readable', $dir, $namespace)
                : sprintf('The templates directory "%s" is not readable', $dir);
            throw new PermissionException($message_not_readable);
        }

        return $dir;
    }
}
