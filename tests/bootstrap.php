<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        return [];
    }
}
