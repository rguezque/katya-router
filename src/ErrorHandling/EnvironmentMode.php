<?php

declare(strict_types=1);

/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezique
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque\ErrorHandling;

use InvalidArgumentException;

/**
 * Represents the application environment mode.
 */
enum EnvironmentMode: string
{
    case Development = 'development';
    case Production = 'production';

    /**
     * Create an environment mode from a string.
     *
     * @param string $value
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromString(string $value): self
    {
        $value = strtolower(trim($value));

        $mode = self::tryFrom($value);

        if ($mode === null) {
            throw new InvalidArgumentException(
                sprintf(
                    "Environment mode must be '%s' or '%s', '%s' given",
                    self::Development->value,
                    self::Production->value,
                    $value
                )
            );
        }

        return $mode;
    }

    /**
     * Check if current mode is development.
     *
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this === self::Development;
    }

    /**
     * Check if current mode is production.
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
