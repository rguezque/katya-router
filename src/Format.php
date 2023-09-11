<?php declare(strict_types = 1);

namespace rguezque;

/**
 * Apply format to strings
 */
class Format {

    /**
     * Add leading slash
     * 
     * @param string $str String to add leading slash
     * @return string
     */
    public static function addLeadingSlash(string $str): string {
        return '/'.self::removeSlashes($str);
    }

    /**
     * Remove leading slash
     * 
     * @param string $str String to remove leading slash
     * @return string
     */
    public static function removeLeadingSlash(string $str): string {
        return ltrim($str,'/\\');
    }

    /**
     * Add trailing slash
     * 
     * @param string $str String to add trailing slash
     * @return string
     */
    public static function addTrailingSlash(string $str): string {
        return self::removeSlashes($str).'/';
    }

    /**
     * Remove trailing slash
     * 
     * @param string $str String to remove trailing slash
     * @return string
     */
    public static function removeTrailingSlash(string $str): string {
        return rtrim($str,'/\\');
    }

    /**
     * Remove leading and trailing slashes
     * 
     * @param string $str String to remove slashes
     * @return string
     */
    public static function removeSlashes(string $str): string {
        return trim($str, '/\\');
    }
}

?>