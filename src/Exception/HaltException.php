<?php

declare(strict_types=1);

namespace rguezque\Exception;

use Exception;
use rguezque\Response;

/**
 * Represents an exception for the special case when the router is stopped and a final Response is returned.
 */
class HaltException extends Exception
{
    private Response $response;

    /**
     * Initialize the exception with a Response object.
     * 
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        parent::__construct('Router execution halted.');
        $this->response = $response;
    }

    /**
     * Returns the Response object attached with this special-case exception.
     * 
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}
