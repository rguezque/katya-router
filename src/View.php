<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use rguezque\Exceptions\FileNotFoundException;

use function rguezque\functions\add_trailing_slash;
use function rguezque\functions\remove_leading_slash;

trait View {

    /**
     * Default path for templates
     * 
     * @var string
     */
    private $viewspath = '';

    /**
     * Set the default path to find for templates
     * 
     * @param string $path Templates directory
     * @return void
     */
    public function setViewsPath(string $path): void {
        $this->viewspath = add_trailing_slash($path);
    }

    /**
     * Response a rendered template
     * 
     * @param string $template The template file
     * @param array $arguments Arguments passed to template
     * @return void
     * @throws FileNotFoundException
     */
    public function render(string $template, array $arguments = []): void {
        $template = remove_leading_slash($template);
        
        if(isset($this->viewspath) && '' !== $this->viewspath) {
            $template = $this->viewspath.$template;
        }

        if(!file_exists($template)) {
            throw new FileNotFoundException(sprintf('The template "%s" wasn\'t found.', $template));
        }

        extract($arguments);
        ob_start();
        include $template;
        $result = ob_get_clean();

        (new Response($result))->send();
    }
}

?>