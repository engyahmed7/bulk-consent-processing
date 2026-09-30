<?php

namespace App\Domains\Broker\Models;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use Illuminate\Database\Eloquent\Model;

class BrokerQueue extends Model
{
    protected $fillable = [
        'purpose',
        'queue_name',
        'exchange',
        'routing_key',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => BrokerQueuePurpose::class,
            'is_active' => 'boolean',
        ];
    }

    public function exchangeName(string $defaultExchange = ''): string
    {
        return $this->exchange ?: $defaultExchange;
    }

    public function routingKeyName(): string
    {
        return $this->routing_key ?: $this->queue_name;
    }
}
