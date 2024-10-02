<?php declare(strict_types=1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

/**
 * Configure and enable CORS (Cross-Origin Resources Sharing)
 * 
 * @method CorsConfig addOrigin(string $origin, array $methods = [], array $headers = []) Add an allowed origin
 */
class CorsConfig {
    /**
     * Allowed origins
     * 
     * @param string[]
     */
    private $origins = [];

    /**
     * Allowed request methods
     * 
     * @param string[]
     */
    private $default_methods = ['GET', 'POST'];

    /**
     * Allowed http headers
     * 
     * @param string[]
     */
    private $default_headers = ['Content-Type', 'Accept', 'Authorization'];

    /**
     * Add an allowed origin
     * 
     * @param string $origin Allowed domain url
     * @param array $methods Allowed request methods from allowed domain
     * @param array $headers Allowed http headers from allowed domain
     * @return CorConfig
     */
    public function addOrigin(string $origin, array $methods = [], array $headers = []): CorsConfig {
        $this->origins[$origin] = [
            'methods' => [] !== $methods ? $methods : $this->default_methods,
            'headers' => [] !== $headers ? $headers : $this->default_headers
        ];

        return $this;
    }

    /**
     * Apply the cors configuration when the class is called like a function
     */
    public function __invoke(Request $request) {
        $server = $request->getServer();

        if($server->valid('HTTP_ORIGIN')) {
            foreach ($this->origins as $origin => $config) {
                if (preg_match('#' . $origin . '#', $server->get('HTTP_ORIGIN'))) {
                    header("Access-Control-Allow-Origin: " . $server->get('HTTP_ORIGIN'));
                    header("Access-Control-Allow-Methods: " . implode(', ', $config['methods']));
                    header("Access-Control-Allow-Headers: " . implode(', ', $config['headers']));
                }
            }
        }
    }
}

?>
