<?php

namespace App\Infrastructure\RabbitMq;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Broker\Services\QueueResolver;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMqPublisher
{
    public function __construct(
        private RabbitMqConnectionFactory $connectionFactory,
        private QueueResolver $queueResolver,
        private RabbitMqSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(BrokerQueuePurpose $purpose, array $payload, int $attempt = 0): void
    {
        $queue = $this->queueResolver->resolve($purpose);
        $connection = $this->connectionFactory->make();
        $channel = $connection->channel();

        try {
            $body = json_encode([
                ...$payload,
                'attempt' => $attempt,
                'published_at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR);

            $message = new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable([
                    'x-retry-count' => $attempt,
                    'x-purpose' => $purpose->value,
                ]),
            ]);

            $channel->basic_publish(
                $message,
                $queue->exchangeName($this->settings->defaultExchange()),
                $queue->routingKeyName(),
            );
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
