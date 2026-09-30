<?php

namespace App\Infrastructure\RabbitMq\Console;

use App\Domains\Broker\Enums\BrokerQueuePurpose;
use App\Domains\Bulk\Handlers\FinalizeBulkHandler;
use App\Domains\Bulk\Handlers\ParseBulkCsvHandler;
use App\Domains\Bulk\Handlers\ProcessBulkChunkHandler;
use App\Infrastructure\RabbitMq\RabbitMqConsumer;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ConsumeByPurposeCommand extends Command
{
    protected $signature = 'bulk:consume {purpose : Broker purpose code (bulk_parse, bulk_validate, bulk_finalize)}';

    protected $description = 'Consume RabbitMQ messages for a broker purpose resolved from the database';

    public function handle(
        RabbitMqConsumer $consumer,
        ParseBulkCsvHandler $parseHandler,
        ProcessBulkChunkHandler $chunkHandler,
        FinalizeBulkHandler $finalizeHandler,
    ): int {
        $purpose = BrokerQueuePurpose::tryFrom((string) $this->argument('purpose'));

        if (! $purpose instanceof BrokerQueuePurpose || $purpose === BrokerQueuePurpose::BulkDlq) {
            throw new InvalidArgumentException('Unsupported purpose. Use bulk_parse, bulk_validate, or bulk_finalize.');
        }

        $handler = match ($purpose) {
            BrokerQueuePurpose::BulkParse => fn (array $payload) => $parseHandler->handle($payload),
            BrokerQueuePurpose::BulkValidate => fn (array $payload) => $chunkHandler->handle($payload),
            BrokerQueuePurpose::BulkFinalize => fn (array $payload) => $finalizeHandler->handle($payload),
            BrokerQueuePurpose::BulkDlq => throw new InvalidArgumentException('Cannot consume DLQ with this command.'),
        };

        $this->info("Consuming purpose [{$purpose->value}] (queue name resolved from database)...");
        $consumer->consume($purpose, $handler);

        return self::SUCCESS;
    }
}
