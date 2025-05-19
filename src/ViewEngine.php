<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use ErrorException;

/**
 * Simple engine that allows render templates
 * 
 * @method string fetch(tring $view, array $data = []) Fetch the template from buffer and return the result as string to be render after
 */
class ViewEngine {
    /**
     * Templates directory
     * 
     * @var string
     */
    private $templates_dir;

    /**
     * Templates cache directory
     * 
     * @var string
     */
    private $cache_dir;

    /**
     * Initialize the template engine
     * 
     * @param string $templates_dir Templates directory
     * @param string $cache_dir Cache directory
     */
    public function __construct(string $templates_dir, string $cache_dir) {
        $this->templates_dir = rtrim($templates_dir, '/\\').'/';
        $this->cache_dir = rtrim($cache_dir, '/\\').'/';
    }

    /**
     * Fetch the template from buffer and return the result as string to be render after
     * 
     * @param string $view The template to render
     * @param array $data Arguments to send for template
     * @return string
     * @throws ErrorException When the file template is not found
     */
    public function fetch(string $view, array $data = []): string {
        $view = trim($view, '/\\ ');
        $template_file = $this->templates_dir . $view . '.php';
        
        if(!file_exists($template_file)) {
            throw new ErrorException(sprintf('The template "%s" is not found', $view));
        }

        $cache_file = $this->cache_dir . $view . '.cache';

        if (!file_exists($cache_file) || filemtime($template_file) > filemtime($cache_file)) {
            $this->compileTemplate($template_file, $cache_file);
        }

        extract($data);

        ob_start();
        include $cache_file;
        $rendered_view = ob_get_clean();

        return $rendered_view;
    }

    /**
     * Compile template and create cache with syntax replacement
     * 
     * @param string $template_file Filename for template
     * @param string $cache_file Flename for cache template file
     * @return void
     */
    private function compileTemplate(string $template_file, string $cache_file): void {
        $template_content = file_get_contents($template_file);        
        $compiled_content = $this->compileTemplateContent($template_content);
        file_put_contents($cache_file, $compiled_content);
    }

    /**
     * Extract the template contents and replace special syntax for print variables
     * 
     * @param string $content Template file contents
     * @return string
     */
    private function compileTemplateContent(string $content): string {
        // Simple template syntax replacement (e.g. {{ variable }} -> <?php echo $variable;?\>\)
        $content = preg_replace('/{{\s*(\w+)\s*}}/', '<?php echo $\\1; ?>', $content);

        return $content;
    }
}
