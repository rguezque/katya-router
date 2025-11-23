<?php declare(strict_types = 1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\functions;

use rguezque\Exceptions\FileNotFoundException;

define('CAST_INT', 91420);
define('CAST_INTEGER', 91420);
define('CAST_FLOAT', 61215120);
define('CAST_STR', 192018);
define('CAST_STRING', 192018);
define('CAST_ARRAY', 1181825);
define('CAST_BOOL', 2151512);
define('CAST_OBJECT', 15210);

if(!function_exists('env')) {
    /**
     * Get an environment variable
     * * This function retrieves the value of an environment variable. If the variable is not set, it returns a default value.
     * 
     * @param string $key The name of the environment variable
     * @param mixed $default The default value to return if the environment variable is not set
     * @param int $cast_to Integer constant that identifies the type of data to be cast to (`CAST_INT`, `CAST_STR`, `CAST_FLOAT`, `CAST_ARRAY`, `CAST_BOOL`, `CAST_OBJECT`)
     * @return mixed The value of the environment variable or the default value
     */
    function env(string $key, mixed $default = null, ?int $cast_to = null): mixed {
        $value = $_ENV[$key] ??= null;
        return isset($value) ? (isset($cast_to) ? cast_to($value, $cast_to) : $value) : $default;
    }
}

if(!function_exists('cast_to')) {
    /**
     * Cast a value to specific data type
     * 
     * @param mixed $value Value to be cast to
     * @param int $cast_type_code Integer constant that identifies the type of data to be cast to (`CAST_INT`, `CAST_STR`, `CAST_FLOAT`, `CAST_ARRAY`, `CAST_BOOL`, `CAST_OBJECT`)
     * @return mixed The value converted
     */
    function cast_to(mixed $value, int $cast_type_code) {
        return match($cast_type_code) {
            91420 => (int)$value,
            61215120 => (float)$value,
            192018 => (string)$value,
            1181825 => (array)$value,
            2151512 => (bool)$value,
            15210 => (object)$value,
            default => $value
        };
    }
}

if(!function_exists('add_trailing_slash')) {
    /**
     * Add a trailing slash
     * * This function adds a trailing slash to a string, ensuring that the string ends with a slash.
     * 
     * @param string $str A string
     * @return string
     */
    function add_trailing_slash(string $str): string {
        return sprintf('%s/', remove_trailing_slash($str));
    }
}

if(!function_exists('remove_trailing_slash')) {
    /**
     * Remove trailing slashes
     * * This function removes trailing slashes from a string, ensuring that the string does not end with a slash or backslash.
     * 
     * @param string $str A string
     * @return string
     */
    function remove_trailing_slash(string $str): string {
        return rtrim($str, '/\\');
    }
}

if(!function_exists('add_leading_slash')) {
    /**
     * Add a leading slash
     * * This function adds a leading slash to a string, ensuring that the string starts with a slash.
     * 
     * @param string $str A string
     * @return string
     */
    function add_leading_slash(string $str): string {
        return sprintf('/%s', remove_leading_slash($str));
    }
}

if(!function_exists('remove_leading_slash')) {
    /**
     * Remove leading slashes
     * * * This function removes leading slashes from a string, ensuring that the string does not start with a slash or backslash.
     * 
     * @param string $str A string
     * @return string
     */
    function remove_leading_slash(string $str): string {
        return ltrim($str, '/\\');
    }
}

if(!function_exists('namespace_format')) {
    /**
     * Return a string like namespace format slashes
     * * This function ensures that the namespace string is properly formatted with a trailing backslash.
     * 
     * @param string $namespace String namespace
     * @return string
     */
    function namespace_format(string $namespace): string {
        return trim($namespace, '\\').'\\';
    }
}

if(!function_exists('str_prepend')) {
    /**
     * Prepend strings to subject string
     * * This function prepends one or more strings to the beginning of a subject string.
     * 
     * @param string $subject String subject
     * @param string $prepend String to prepend (first declared, first prepended)
     */
    function str_prepend(string $subject, string ...$prepend): string {
        return implode('', array_reverse($prepend)).$subject;
    }
}

if(!function_exists('str_append')) {
    /**
     * Append strings to subject string
     * * This function appends one or more strings to the end of a subject string.
     * 
     * @param string $subject String subject
     * @param string $append String to append
     */
    function str_append(string $subject, string ...$append): string {
        return $subject.implode('', $append);
    }
}

if(!function_exists('str_path')) {
    /**
     * Clean and prepare a string path
     * * This function ensures that the path starts with a leading slash and does not end with a trailing slash.
     * 
     * @param string $path String path
     * @return string
     */
    function str_path(string $path): string {
        return add_leading_slash(remove_trailing_slash($path));
    }
}

if(!function_exists('is_assoc_array')) {
    /**
     * Return true if the evaluated array is associative
     * * An associative array is an array where the keys are not sequential integers starting from 0.
     * 
     * @param array $arr Array to evaluate
     * @return bool
     */
    function is_assoc_array(array $arr): bool {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

if(!function_exists('json_file_get_contents')) {
    /**
     * Reads entire json file into an associative array
     * * This function reads a JSON file and decodes its contents into an associative array.
     * 
     * @param string $file Json file path
     * @return array
     * @throws FileNotFoundException
     */
    function json_file_get_contents(string $file): array {
        if(!file_exists($file)) {
            throw new FileNotFoundException(sprintf('The file %s wasn\'t found.', $file));
        }

        $contents = file_get_contents($file);
        return json_decode($contents, true);
    }
}

if(!function_exists('unsetcookie')) {
    /**
     * Delete a cookie
     * * This function sets the cookie with an expiration time in the past, effectively deleting it.
     * 
     * @param string $name Cookie name
     * @return bool True on success, otherwise false
     */
    function unsetcookie(string $name): bool {
        return setcookie($name, '', time()-3600);
    }
}

if(!function_exists('getcookie')) {
    /**
     * Get a cookie value
     * * This function retrieves the value of a cookie by its name. If the cookie does not exist, it returns a default value.
     * 
     * @param string $name Cookie name
     * @return ?string
     */
    function getcookie(string $name, $default = null): ?string {
        return $_COOKIE[$name] ?? $default;
    }
}

if(!function_exists('equals')) {
    /**
     * Returns true if two strings are equals, otherwise false
     * * This function uses `strcmp` to compare the two strings.
     * 
     * @param string $str_one First string
     * @param string $str_two Second string
     * @return bool
     */
    function equals(string $str_one, string $str_two): bool {
        return strcmp($str_one, $str_two) === 0;
    }
}

if(!function_exists('is_localhost')) {
    /**
     * Returns `true` if the client IP is in localhost, `false` en caso contrario.
     * 
     * @return bool
     */
    function is_localhost(): bool {
        // List of IPs that correspond to localhost
        $white_list = array(
            '127.0.0.1', // IPv4
            '::1'        // IPv6
        );

        // It checks if the client's IP is in the list or the server name is localhost
        if (in_array($_SERVER['REMOTE_ADDR'], $white_list) || $_SERVER['SERVER_NAME'] === 'localhost') {
            return true;
        }

        return false;
    }
}
