<?php

namespace Modules\Pharmacy\Exceptions;

class InsufficientStockException extends \RuntimeException
{
    public function __construct(string $message = 'Insufficient stock.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
