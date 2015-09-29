<?php

namespace Graze\Gigya\Exceptions;

use Exception;
use Psr\Http\Message\ResponseInterface;

class UnknownResponseException extends Exception
{
    public function __construct(ResponseInterface $response, Exception $e = null)
    {
        $message = "The contents of the response could not be determined.\n  Body:\n" . $response->getBody();

        if ($e) {
            $message .= "\n" . $e->getMessage();
        }

        parent::__construct($message, 0, $e);
    }
}