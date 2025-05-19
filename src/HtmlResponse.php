<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Represent an HTML response for views templates
 */
class HtmlResponse extends Response {
    public function __construct(string $content, int $status_code = HttpStatus::HTTP_OK, array $headers = []) {
        parent::__construct($content, $status_code, $headers);
        $this->headers->set('Content-Type', 'text/html;charset=utf-8');
    }
}

?>