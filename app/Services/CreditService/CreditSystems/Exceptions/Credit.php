<?php

namespace App\Services\CreditService\CreditSystems\Exceptions;

use Exception;
use Throwable;

/**
 * Class Credit
 * @package App\Services\CreditService\CreditSystems\Exceptions
 */
class Credit extends Exception
{
    public function __construct($message = 'Error creating credit', $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
