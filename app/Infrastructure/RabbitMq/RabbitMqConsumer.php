<?php

namespace App\Infrastructure\RabbitMq;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Broker\Services\QueueResolver;
use Closure;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMqConsumer
{
    public function __construct(
        private RabbitMqConnectionFactory $connectionFactory,
        private QueueResolver $queueResolver,
        private RabbitMqPublisher $publisher,
        private RabbitMqSettings $settings,
    ) {}

    /**
     * @param  Closure(array<string, mixed>): void  $handler
     */
    public function consume(BrokerQueuePurpose $purpose, Closure $handler): void
    {
        $queue = $this->queueResolver->resolve($purpose);
        $connection = $this->connectionFactory->make();
        $channel = $connection->channel();

        $channel->basic_qos(0, $this->settings->integer('prefetch_count'), false);

        $channel->basic_consume(
            queue: $queue->queue_name,
            consumer_tag: '',
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($handler, $purpose): void {
                $this->handleMessage($message, $handler, $purpose);
            },
        );

        Log::info('RabbitMQ consumer started', [
            'purpose' => $purpose->value,
            'queue' => $queue->queue_name,
        ]);

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    /**
     * @param  Closure(array<string, mixed>): void  $handler
     */
    private function handleMessage(AMQPMessage $message, Closure $handler, BrokerQueuePurpose $purpose): void
    {
        $attempt = $this->retryCount($message);

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $handler($payload);
            $message->ack();
        } catch (Throwable $exception) {
            Log::error('RabbitMQ message handling failed', [
                'purpose' => $purpose->value,
                'attempt' => $attempt,
                'error' => $exception->getMessage(),
            ]);

            $maxRetries = $this->settings->integer('max_retries');

            if ($attempt + 1 < $maxRetries) {
                $message->nack(false, false);

                try {
                    /** @var array<string, mixed> $payload */
                    $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $this->publisher->publish($purpose, $payload, $attempt + 1);
                } catch (Throwable $publishException) {
                    Log::error('Failed to requeue message', [
                        'error' => $publishException->getMessage(),
                    ]);
                }

                return;
            }

            $message->nack(false, false);

            try {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $this->publisher->publish(BrokerQueuePurpose::BulkDlq, [
                    ...$payload,
                    'failed_purpose' => $purpose->value,
                    'error' => $exception->getMessage(),
                ], $attempt + 1);
            } catch (Throwable $dlqException) {
                Log::error('Failed to publish to DLQ purpose', [
                    'error' => $dlqException->getMessage(),
                ]);
            }
        }
    }

    private function retryCount(AMQPMessage $message): int
    {
        $headers = $message->get('application_headers');

        if (! $headers instanceof AMQPTable) {
            return 0;
        }

        $native = $headers->getNativeData();

        return (int) ($native['x-retry-count'] ?? 0);
    }
}
