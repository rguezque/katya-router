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

/**
 * Represent a redirection
 *
 * This class extends the Response class to provide a specific implementation for redirects
 */
final class RedirectResponse extends Response
{

    /**
     * Valid HTTP codes for redirects.
     */
    private const REDIRECT_STATUS_CODES = [
        301, // Moved Permanently
        302, // Found
        303, // See Other
        307, // Temporary Redirect
        308, // Permanent Redirect
    ];

    public function __construct(string $location, int $status_code = HttpStatus::HTTP_SEE_OTHER)
    {
        if (!in_array($status_code, self::REDIRECT_STATUS_CODES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'HTTP code "%d" is not a valid redirect code.',
                    $status_code
                )
            );
        }

        parent::__construct(status_code: $status_code, headers: ['Location' => $location]);
    }
}
