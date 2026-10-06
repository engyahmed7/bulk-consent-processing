<?php

namespace Modules\Bulk\Shared\Messages;

use InvalidArgumentException;
use Modules\Bulk\Shared\Messaging\BulkMessaging;
use Modules\Core\Features\RabbitMQ\Contracts\Message;

abstract readonly class BulkMessage implements Message
{
    public function __construct(public int $id)
    {
        if ($id < 1) {
            throw new InvalidArgumentException('A bulk message id must be positive.');
        }
    }

    final public function exchange(): string
    {
        return BulkMessaging::EVENTS_EXCHANGE;
    }

    abstract public function routingKey(): string;

    /**
     * @return array<string, mixed>
     */
    final public function toPayload(): array
    {
        return [static::payloadKey() => $this->id];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    final public static function fromPayload(array $payload): static
    {
        $id = $payload[static::payloadKey()] ?? null;

        if (! is_int($id) || $id < 1) {
            throw new InvalidArgumentException('The bulk message payload has an invalid id.');
        }

        return new static($id);
    }

    abstract protected static function payloadKey(): string;
}
