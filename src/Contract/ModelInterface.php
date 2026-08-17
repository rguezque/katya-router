<?php

declare(strict_types=1);

namespace rguezque\Contract;

/**
 * This contract define the basic initialization for a model class.
 */
interface ModelInterface
{
    public function __construct(ConnectionInterface $connection);
}
