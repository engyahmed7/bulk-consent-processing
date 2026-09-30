<?php

namespace App\Domains\Broker\Exceptions;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use RuntimeException;

class BrokerQueueNotConfiguredException extends RuntimeException
{
    public static function forPurpose(BrokerQueuePurpose $purpose): self
    {
        return new self("No active broker queue registered for purpose [{$purpose->value}]. Register it via the dashboard API.");
    }
}
