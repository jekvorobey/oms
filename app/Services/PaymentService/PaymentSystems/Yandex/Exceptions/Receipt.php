<?php

namespace App\Services\PaymentService\PaymentSystems\Yandex\Exceptions;

use Exception;
use Throwable;

class Receipt extends Exception
{
    public function __construct($message = 'Error creating receipt', $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
