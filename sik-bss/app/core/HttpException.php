<?php
namespace App\Core;

class HttpException extends \Exception
{
    public function __construct(public int $status, string $message = '')
    {
        parent::__construct($message);
    }
}
