<?php

namespace App\Services\PaymentService\PaymentSystems\Exceptions;

use Exception;
use Throwable;

class Payment extends Exception
{
    public function __construct($message = 'Error creating payment', $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
