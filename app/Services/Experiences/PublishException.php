<?php

namespace App\Services\Experiences;

use RuntimeException;

class PublishException extends RuntimeException
{
    /**
     * @param  string  $reason  plan | invalid | unavailable
     */
    public function __construct(string $message, public readonly string $reason = 'unavailable')
    {
        parent::__construct($message);
    }
}
