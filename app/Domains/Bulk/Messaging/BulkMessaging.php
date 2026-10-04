<?php

namespace App\Domains\Bulk\Messaging;

use App\Domains\Bulk\Handlers\FinalizeBulkHandler;
use App\Domains\Bulk\Handlers\ParseBulkCsvHandler;
use App\Domains\Bulk\Handlers\ProcessBulkChunkHandler;
use Modules\Core\Features\RabbitMQ\Contracts\ModuleMessaging;
use Modules\Core\Features\RabbitMQ\Scaling\FixedConsumerScaling;
use Modules\Core\Features\RabbitMQ\Topology\ExchangeDefinition;
use Modules\Core\Features\RabbitMQ\Topology\ExchangeType;
use Modules\Core\Features\RabbitMQ\Topology\QueueDefinition;

final class BulkMessaging implements ModuleMessaging
{
    public const string EVENTS_EXCHANGE = 'bulk.events';

    public const string PARSE_QUEUE = 'bulk.parse';

    public const string VALIDATE_QUEUE = 'bulk.validate';

    public const string FINALIZE_QUEUE = 'bulk.finalize';

    public const string PARSE_REQUESTED = 'bulk.parse.requested';

    public const string CHUNK_REQUESTED = 'bulk.chunk.requested';

    public const string FINALIZE_REQUESTED = 'bulk.finalize.requested';

    public function exchanges(): array
    {
        return [
            new ExchangeDefinition(self::EVENTS_EXCHANGE, ExchangeType::Topic),
        ];
    }

    public function queues(): array
    {
        return [
            new QueueDefinition(
                name: self::PARSE_QUEUE,
                exchange: self::EVENTS_EXCHANGE,
                routingKeys: [self::PARSE_REQUESTED],
                handler: ParseBulkCsvHandler::class,
            ),
            new QueueDefinition(
                name: self::VALIDATE_QUEUE,
                exchange: self::EVENTS_EXCHANGE,
                routingKeys: [self::CHUNK_REQUESTED],
                handler: ProcessBulkChunkHandler::class,
                scaling: new FixedConsumerScaling(minConsumers: 1, maxConsumers: 3),
            ),
            new QueueDefinition(
                name: self::FINALIZE_QUEUE,
                exchange: self::EVENTS_EXCHANGE,
                routingKeys: [self::FINALIZE_REQUESTED],
                handler: FinalizeBulkHandler::class,
            ),
        ];
    }
}
