<?php

namespace App\Domains\Broker\Services;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Broker\Exceptions\BrokerQueueNotConfiguredException;
use App\Domains\Broker\Models\BrokerQueue;

class QueueResolver
{
    public function resolve(BrokerQueuePurpose $purpose): BrokerQueue
    {
        $queue = BrokerQueue::query()
            ->where('purpose', $purpose->value)
            ->where('is_active', true)
            ->first();

        if (! $queue instanceof BrokerQueue) {
            throw BrokerQueueNotConfiguredException::forPurpose($purpose);
        }

        return $queue;
    }
}
