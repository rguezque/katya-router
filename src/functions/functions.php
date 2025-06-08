<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\functions;

use rguezque\Exceptions\FileNotFoundException;

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

/**
 * Return true if a string has a specific prefix
 * * * This function checks if the given string starts with the specified prefix.
 * 
 * @param string $haystack String to evaluate
 * @param string $needle Prefix to search
 * @return bool
 */
if(!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === substr($haystack, 0, strlen($needle));
    }
}


/**
 * Return true if a string has a specific suffix
 * * This function checks if the given string ends with the specified suffix.
 * 
 * @param string $haystack String to evaluate
 * @param string $needle Suffix to search
 * @return bool
 */
if(!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === substr($haystack, -strlen($needle));
    }
}

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

/**
 * Return true if the evaluated array is associative
 * * An associative array is an array where the keys are not sequential integers starting from 0.
 * 
 * @param mixed $value Value to evaluate
 * @return bool
 */
function is_assoc_array($value): bool {
    if(!is_array($value)) return false;
    if ([] === $value) return false;
    
    return array_keys($value) !== range(0, count($value) - 1);
}

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

/**
 * Returns true if two strings are equals, otherwise false
 * * This function uses `strcmp` to compare the two strings.
 * 
 * @param string $str_one First string
 * @param string $str_two Second string
 * @return bool
 */
if(!function_exists('equals')) {
    function equals(string $str_one, string $str_two): bool {
        return strcmp($str_one, $str_two) === 0;
    }
}

