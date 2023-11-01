<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\functions;

use rguezque\Exceptions\FileNotFoundException;

/**
 * Add a trailing slash
 * 
 * @param string $str A string
 * @return string
 */
function add_trailing_slash(string $str): string {
    return sprintf('%s/', remove_trailing_slash($str));
}

/**
 * Remove trailing slashes
 * 
 * @param string $str A string
 * @return string
 */
function remove_trailing_slash(string $str): string {
    return rtrim($str, '/\\');
}

/**
 * Add a leading slash
 * 
 * @param string $str A string
 * @return string
 */
function add_leading_slash(string $str): string {
    return sprintf('/%s', remove_leading_slash($str));
}

/**
 * Remove leading slashes
 * 
 * @param string $str A string
 * @return string
 */
function remove_leading_slash(string $str): string {
    return ltrim($str, '/\\');
}

/**
 * Return a string like namespace format slashes
 * 
 * @param string $namespace String namespace
 * @return string
 */
function namespace_format(string $namespace): string {
    return trim($namespace, '\\').'\\';
}

/**
 * Return true if a string has a specific prefix
 * 
 * @param string $haystack String to evaluate
 * @param string $needle Prefix to search
 * @return bool
 */
function str_starts_with(string $haystack, string $needle): bool {
    return $needle === substr($haystack, 0, strlen($needle));
}

/**
 * Return true if a string has a specific suffix
 * 
 * @param string $haystack String to evaluate
 * @param string $needle Suffix to search
 * @return bool
 */
function str_ends_with(string $haystack, string $needle): bool {
    return $needle === substr($haystack, -strlen($needle));
}

/**
 * Prepend strings to subject string
 * 
 * @param string $subject String subject
 * @param string $prepend String to prepend (first declared, first prepended)
 */
function str_prepend(string $subject, string ...$prepend): string {
    return implode('', array_reverse($prepend)).$subject;
}

/**
 * Append strings to subject string
 * 
 * @param string $subject String subject
 * @param string $append String to append
 */
function str_append(string $subject, string ...$append): string {
    return $subject.implode('', $append);
}

/**
 * Clean and prepare a string path
 * 
 * @param string $path String path
 * @return string
 */
function str_path(string $path): string {
    return add_leading_slash(remove_trailing_slash($path));
}

/**
 * Return true if the evaluated array is associative
 * 
 * @param mixed $value Value to evaluate
 * @return bool
 */
function is_assoc_array($value): bool {
    if(!is_array($value)) return false;
    if (array() === $value) return false;
    
    return array_keys($value) !== range(0, count($value) - 1);
}

/**
 * Reads entire json file into an associative array
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
 * 
 * @param string $name Cookie name
 * @return bool True on success, otherwise false
 */
function unsetcookie(string $name): bool {
    return setcookie($name, '', time()-3600);
}

/**
 * Returns true if two strings are equals, otherwise false
 * 
 * @param string $strone First string
 * @param string $strtwo Second string
 * @return bool
 */
function equals(string $strone, string $strtwo): bool {
    return strcmp($strone, $strtwo) === 0;
}

/**
 * Generates RFC 4122 compliant version 4 UUIDs
 * 
 * @param string $data Data passed into the function
 * @return string
 */
function uuidv4(?string $data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
