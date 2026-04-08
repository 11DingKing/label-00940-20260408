<?php

declare(strict_types=1);

namespace App\Exception;

use Hyperf\Server\Exception\ServerException;

class BusinessException extends ServerException
{
    public function __construct(int $code = 0, string $message = '', \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
