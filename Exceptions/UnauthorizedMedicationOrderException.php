<?php

namespace Modules\Pharmacy\Exceptions;

class UnauthorizedMedicationOrderException extends \RuntimeException
{
    public function __construct(string $message = 'Unauthorized medication order.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
